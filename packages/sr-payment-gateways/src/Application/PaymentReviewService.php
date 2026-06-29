<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Application;

use DateTimeImmutable;
use RuntimeException;
use StockResource\PaymentGateways\Gateway\ManualQr\ManualQrException;
use StockResource\PaymentGateways\Gateway\ManualQr\PaymentApprovalService;
use StockResource\PaymentGateways\Gateway\ManualQr\PaymentSubmission as ManualSubmission;
use StockResource\PaymentGateways\Gateway\ManualQr\TransactionFingerprint as ManualTransactionFingerprint;
use StockResource\PaymentGateways\Submission\PaymentSubmission;
use StockResource\PaymentGateways\Submission\PaymentSubmissionException;
use StockResource\PaymentGateways\Submission\PaymentSubmissionRepository;
use StockResource\PaymentGateways\Submission\SubmissionState;

final readonly class PaymentReviewService
{
    private mixed $nowProvider;

    public function __construct(
        private PaymentSubmissionRepository $repository,
        private PaymentApprovalService $approvalService,
        private mixed $completeOrder,
        private mixed $canApprove,
        mixed $nowProvider = null,
    ) {
        if (! is_callable($this->completeOrder)) {
            throw new RuntimeException('completeOrder callback is required.');
        }

        if (! is_callable($this->canApprove)) {
            throw new RuntimeException('canApprove callback is required.');
        }

        $this->nowProvider = $nowProvider ?? static fn (): string => (new DateTimeImmutable('now'))->format(DATE_ATOM);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function approve(
        int $submissionId,
        int $reviewerId,
        int $expectedLockVersion,
        string $idempotencyKey,
        string $externalReference,
        string $verifiedAmount,
        string $verifiedPaidAt,
        array $extra = [],
    ): array {
        $submission = $this->repository->findById($submissionId);
        if ($submission === null) {
            throw new PaymentReviewException('submission_not_found', 'Payment submission not found.');
        }

        $idempotencyKey = trim($idempotencyKey);
        $externalReference = trim($externalReference);
        $verifiedAmount = self::normalizeMoney($verifiedAmount);

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 255) {
            throw new PaymentReviewException('invalid_idempotency_key', 'idempotency_key is required and must be <= 255 chars.');
        }

        if ($externalReference === '' || strlen($externalReference) < 4 || strlen($externalReference) > 128) {
            throw new PaymentReviewException('invalid_external_reference', 'external_reference must be 4-128 chars.');
        }

        if (strtotime($verifiedPaidAt) === false) {
            throw new PaymentReviewException('invalid_verified_paid_at', 'verified_paid_at must be valid datetime.');
        }

        if (! ((bool) ($this->canApprove)($reviewerId, $submission))) {
            throw new PaymentReviewException('permission_denied', 'Reviewer has no financial approval permission.');
        }

        if ($submission->state === SubmissionState::Approved) {
            return $this->replayApproved(
                submission: $submission,
                reviewerId: $reviewerId,
                expectedLockVersion: $expectedLockVersion,
                idempotencyKey: $idempotencyKey,
                externalReference: $externalReference,
                verifiedAmount: $verifiedAmount,
                verifiedPaidAt: $verifiedPaidAt,
            );
        }

        if ($submission->state !== SubmissionState::UnderReview) {
            throw new PaymentReviewException('state_conflict', 'Only under_review submissions can be approved.');
        }

        if ($expectedLockVersion < 0) {
            throw new PaymentReviewException('lock_version_mismatch', 'Expected lock version must be a non-negative integer.');
        }

        $this->assertExpectedAmount($submission, $verifiedAmount);

        $fingerprint = $this->fingerprint($submission, $externalReference, $verifiedAmount, $verifiedPaidAt);
        if ($submission->transactionFingerprint !== null) {
            if ($submission->transactionFingerprint !== $fingerprint) {
                throw new PaymentReviewException('approval_fingerprint_mismatch', 'Submission already has another transaction fingerprint.');
            }

            $this->assertReplayFingerprintStable($submission, $externalReference, $verifiedAmount, $verifiedPaidAt);
        }

        $this->assertTransactionFingerprintUnique($submission, $fingerprint);

        try {
            $manualSubmission = (new ManualSubmissionAdapter($submission))->toManual();
            $approved = $this->approvalService->approve(
                submission: $manualSubmission,
                expectedLockVersion: $expectedLockVersion,
                idempotencyKey: $idempotencyKey,
                proofHash: $submission->proofSha256,
                billFingerprint: $fingerprint,
                billAmount: $verifiedAmount,
            );
        } catch (ManualQrException $exception) {
            throw match ($exception->codeName) {
                'lock_version_mismatch' => new PaymentReviewException('lock_version_mismatch', $exception->getMessage()),
                'amount_mismatch' => new PaymentReviewException('amount_mismatch', $exception->getMessage()),
                'real_bill_required' => new PaymentReviewException('invalid_bill', $exception->getMessage()),
                'invalid_transition' => new PaymentReviewException('state_conflict', $exception->getMessage()),
                default => new PaymentReviewException('approval_failed', $exception->getMessage()),
            };
        }

        $next = $this->mergeApproval(
            submission: $submission,
            approvedSubmission: $approved['submission'],
            idempotencyKey: $idempotencyKey,
            externalReference: $externalReference,
            verifiedAmount: $verifiedAmount,
            verifiedPaidAt: $verifiedPaidAt,
            reviewerId: $reviewerId,
            fingerprint: $fingerprint,
            decisionCode: self::normalizeNullableString($extra['decision_code'] ?? null) ?? 'APPROVED',
            internalNote: self::normalizeNullableString($extra['internal_note'] ?? null),
            userMessage: self::normalizeNullableString($extra['user_message'] ?? null),
        );

        try {
            $updated = $this->repository->update($next, $expectedLockVersion);
        } catch (PaymentSubmissionException $exception) {
            if ($exception->codeName === 'duplicate_transaction_fingerprint') {
                throw new PaymentReviewException('duplicate_transaction_fingerprint', $exception->getMessage());
            }

            if ($exception->codeName === 'lock_version_mismatch') {
                throw new PaymentReviewException('lock_version_mismatch', $exception->getMessage());
            }

            throw new PaymentReviewException($exception->codeName, $exception->getMessage());
        }

        $completed = $this->completeOrderIfNeeded(
            $updated,
            (string) $verifiedAmount,
            (string) $verifiedPaidAt,
        );

        return [
            'submission' => $updated,
            'complete_order' => $completed,
            'idempotent_replay' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function replayApproved(
        PaymentSubmission $submission,
        int $reviewerId,
        int $expectedLockVersion,
        string $idempotencyKey,
        string $externalReference,
        string $verifiedAmount,
        string $verifiedPaidAt,
    ): array {
        if ($expectedLockVersion !== $submission->lockVersion) {
            throw new PaymentReviewException('lock_version_mismatch', 'Expected lock version mismatch.');
        }

        if ($submission->approvalIdempotencyKeyHash !== hash('sha256', $idempotencyKey)) {
            throw new PaymentReviewException('idempotency_conflict', 'Different idempotency key used for approved submission.');
        }

        if (! $this->submissionFingerprintMatch($submission, $externalReference, $verifiedAmount, $verifiedPaidAt)) {
            throw new PaymentReviewException('approval_fingerprint_mismatch', 'Approved submission does not match replay payload.');
        }

        if ($submission->externalReference !== $externalReference) {
            throw new PaymentReviewException('approval_fingerprint_mismatch', 'Approved submission external_reference mismatch.');
        }

        if ($submission->reviewerId !== null && $submission->reviewerId !== $reviewerId) {
            throw new PaymentReviewException('permission_conflict', 'Submission was approved by another reviewer.');
        }

        $completed = $this->completeOrderIfNeeded($submission, $verifiedAmount, $verifiedPaidAt);

        return [
            'submission' => $submission,
            'complete_order' => $completed,
            'idempotent_replay' => true,
        ];
    }

    private function completeOrderIfNeeded(PaymentSubmission $submission, string $verifiedAmount, string $verifiedPaidAt): bool
    {
        if ($submission->state !== SubmissionState::Approved) {
            throw new PaymentReviewException('not_approved', 'cannot complete not-approved submission.');
        }

        try {
            return (bool) ($this->completeOrder)(
                $submission->id,
                $submission->orderId,
                $submission->expectedAmount,
                $verifiedAmount,
                $verifiedPaidAt,
            );
        } catch (RuntimeException $exception) {
            throw new PaymentReviewException('complete_order_failed', 'Order completion callback failed: '.$exception->getMessage());
        }
    }

    private function assertTransactionFingerprintUnique(PaymentSubmission $submission, string $fingerprint): void
    {
        $exists = $this->repository->findByTransactionFingerprint($fingerprint);
        if ($exists !== null && $exists->id !== $submission->id) {
            throw new PaymentReviewException('duplicate_transaction_fingerprint', 'transaction fingerprint already used by another submission.');
        }
    }

    private function assertExpectedAmount(PaymentSubmission $submission, string $verifiedAmount): void
    {
        if (self::normalizeMoney($submission->expectedAmount) !== $verifiedAmount) {
            throw new PaymentReviewException('amount_mismatch', 'verified_amount must match expected amount.');
        }
    }

    private function assertReplayFingerprintStable(
        PaymentSubmission $submission,
        string $externalReference,
        string $verifiedAmount,
        string $verifiedPaidAt,
    ): void {
        if (! $this->submissionFingerprintMatch($submission, $externalReference, $verifiedAmount, $verifiedPaidAt)) {
            throw new PaymentReviewException('approval_fingerprint_mismatch', 'Submission fingerprint does not match replay payload.');
        }
    }

    private function mergeApproval(
        PaymentSubmission $submission,
        ManualSubmission $approvedSubmission,
        string $idempotencyKey,
        string $externalReference,
        string $verifiedAmount,
        string $verifiedPaidAt,
        int $reviewerId,
        string $fingerprint,
        string $decisionCode,
        ?string $internalNote,
        ?string $userMessage,
    ): PaymentSubmission {
        return PaymentSubmission::fromArray(array_replace(
            $submission->toArray(),
            [
                'state' => $approvedSubmission->state,
                'lock_version' => $approvedSubmission->lockVersion,
                'transaction_fingerprint' => $fingerprint,
                'verified_amount' => $verifiedAmount,
                'verified_paid_at' => $verifiedPaidAt,
                'approval_idempotency_key_hash' => hash('sha256', $idempotencyKey),
                'reviewer_id' => $reviewerId,
                'decision_code' => $decisionCode,
                'internal_note' => $internalNote,
                'user_message' => $userMessage,
                'external_reference' => $externalReference,
                'reviewed_at' => $this->now(),
            ],
        ));
    }

    private function submissionFingerprintMatch(
        PaymentSubmission $submission,
        string $externalReference,
        string $verifiedAmount,
        string $verifiedPaidAt,
    ): bool {
        return $submission->transactionFingerprint === $this->fingerprint($submission, $externalReference, $verifiedAmount, $verifiedPaidAt)
            && $submission->verifiedAmount === $verifiedAmount
            && $submission->verifiedPaidAt === $verifiedPaidAt;
    }

    private function fingerprint(PaymentSubmission $submission, string $externalReference, string $verifiedAmount, string $verifiedPaidAt): string
    {
        return ManualTransactionFingerprint::fromBill(
            channel: $submission->channel,
            accountKey: $submission->accountKey ?? 'unknown',
            externalReference: $externalReference,
            amount: $verifiedAmount,
            paidAt: $verifiedPaidAt,
        );
    }

    private static function normalizeMoney(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^(0|[1-9][0-9]*)(\\.[0-9]{1,2})?$/', $value)) {
            throw new PaymentReviewException('invalid_money', 'invalid money value.');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private static function normalizeNullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function now(): string
    {
        return (string) ($this->nowProvider)();
    }
}

final class PaymentReviewException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }
}

final class ManualSubmissionAdapter
{
    public function __construct(private readonly PaymentSubmission $submission)
    {
    }

    public function toManual(): ManualSubmission
    {
        $manual = ManualSubmission::draft(
            orderId: $this->submission->orderId,
            userId: $this->submission->userId,
            amount: $this->submission->expectedAmount,
            currency: $this->submission->currency,
            proofHash: $this->submission->proofSha256,
        );

        $manual = $manual->withState($this->submission->state->value);
        if ($this->submission->transactionFingerprint === null || $this->submission->verifiedAmount === null || $this->submission->approvalIdempotencyKeyHash === null) {
            return $manual;
        }

        return $manual->withApproval(
            billFingerprint: $this->submission->transactionFingerprint,
            billAmount: $this->submission->verifiedAmount,
            idempotencyKey: $this->submission->approvalIdempotencyKeyHash,
        );
    }
}
