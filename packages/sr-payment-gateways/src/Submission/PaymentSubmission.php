<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Submission;

use RuntimeException;

final readonly class PaymentSubmission
{
    private function __construct(
        public int $id,
        public string $submissionKey,
        public int $orderId,
        public int $userId,
        public SubmissionState $state,
        public string $channel,
        public ?string $accountKey,
        public string $currency,
        public string $expectedAmount,
        public string $reportedAmount,
        public string $reportedPaidAt,
        public ?string $payerNote,
        public string $proofStorageKey,
        public string $proofSha256,
        public string $proofMimeType,
        public int $proofFileSize,
        public ?string $proofDeletedAt,
        public ?string $externalReference,
        public ?string $transactionFingerprint,
        public ?string $verifiedAmount,
        public ?string $verifiedPaidAt,
        public ?string $approvalIdempotencyKeyHash,
        public ?int $reviewerId,
        public ?string $claimedAt,
        public ?string $decisionCode,
        public ?string $internalNote,
        public ?string $userMessage,
        public int $lockVersion,
        public string $submittedAt,
        public ?string $reviewedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
        $this->assertInvariant();
    }

    public static function create(
        string $submissionKey,
        int $orderId,
        int $userId,
        string $channel,
        string $expectedAmount,
        string $reportedAmount,
        string $reportedPaidAt,
        string $proofStorageKey,
        string $proofSha256,
        string $proofMimeType,
        int $proofFileSize,
        string $nowUtc,
        ?string $accountKey = null,
        ?string $payerNote = null,
        ?string $externalReference = null,
        string $currency = 'CNY',
    ): self {
        return new self(
            id: 0,
            submissionKey: $submissionKey,
            orderId: $orderId,
            userId: $userId,
            state: SubmissionState::Submitted,
            channel: $channel,
            accountKey: $accountKey,
            currency: $currency,
            expectedAmount: $expectedAmount,
            reportedAmount: $reportedAmount,
            reportedPaidAt: $reportedPaidAt,
            payerNote: $payerNote,
            proofStorageKey: $proofStorageKey,
            proofSha256: $proofSha256,
            proofMimeType: $proofMimeType,
            proofFileSize: $proofFileSize,
            proofDeletedAt: null,
            externalReference: $externalReference,
            transactionFingerprint: null,
            verifiedAmount: null,
            verifiedPaidAt: null,
            approvalIdempotencyKeyHash: null,
            reviewerId: null,
            claimedAt: null,
            decisionCode: null,
            internalNote: null,
            userMessage: null,
            lockVersion: 0,
            submittedAt: $nowUtc,
            reviewedAt: null,
            createdAt: $nowUtc,
            updatedAt: $nowUtc,
        );
    }

    public static function fromArray(array $data): self
    {
        $state = SubmissionState::tryFrom(trim((string) ($data['state'] ?? '')));

        if ($state === null) {
            throw PaymentSubmissionException::invalidState((string) ($data['state'] ?? ''));
        }

        return new self(
            id: max(0, (int) ($data['id'] ?? 0)),
            submissionKey: trim((string) ($data['submission_key'] ?? '')),
            orderId: max(0, (int) ($data['order_id'] ?? 0)),
            userId: max(0, (int) ($data['user_id'] ?? 0)),
            state: $state,
            channel: trim((string) ($data['channel'] ?? '')),
            accountKey: self::nullableString($data['account_key'] ?? null),
            currency: trim((string) ($data['currency'] ?? 'CNY')),
            expectedAmount: trim((string) ($data['expected_amount'] ?? '0')),
            reportedAmount: trim((string) ($data['reported_amount'] ?? '0')),
            reportedPaidAt: trim((string) ($data['reported_paid_at'] ?? '')),
            payerNote: self::nullableString($data['payer_note'] ?? null),
            proofStorageKey: trim((string) ($data['proof_storage_key'] ?? '')),
            proofSha256: trim((string) ($data['proof_sha256'] ?? '')),
            proofMimeType: trim((string) ($data['proof_mime_type'] ?? '')),
            proofFileSize: max(0, (int) ($data['proof_file_size'] ?? 0)),
            proofDeletedAt: self::nullableString($data['proof_deleted_at'] ?? null),
            externalReference: self::nullableString($data['external_reference'] ?? null),
            transactionFingerprint: self::nullableString($data['transaction_fingerprint'] ?? null),
            verifiedAmount: self::nullableString($data['verified_amount'] ?? null),
            verifiedPaidAt: self::nullableString($data['verified_paid_at'] ?? null),
            approvalIdempotencyKeyHash: self::nullableString($data['approval_idempotency_key_hash'] ?? null),
            reviewerId: isset($data['reviewer_id']) ? (int) $data['reviewer_id'] : null,
            claimedAt: self::nullableString($data['claimed_at'] ?? null),
            decisionCode: self::nullableString($data['decision_code'] ?? null),
            internalNote: self::nullableString($data['internal_note'] ?? null),
            userMessage: self::nullableString($data['user_message'] ?? null),
            lockVersion: max(0, (int) ($data['lock_version'] ?? 0)),
            submittedAt: trim((string) ($data['submitted_at'] ?? '')),
            reviewedAt: self::nullableString($data['reviewed_at'] ?? null),
            createdAt: trim((string) ($data['created_at'] ?? '')),
            updatedAt: trim((string) ($data['updated_at'] ?? '')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'submission_key' => $this->submissionKey,
            'order_id' => $this->orderId,
            'user_id' => $this->userId,
            'state' => $this->state->value,
            'channel' => $this->channel,
            'account_key' => $this->accountKey,
            'currency' => $this->currency,
            'expected_amount' => $this->expectedAmount,
            'reported_amount' => $this->reportedAmount,
            'reported_paid_at' => $this->reportedPaidAt,
            'payer_note' => $this->payerNote,
            'proof_storage_key' => $this->proofStorageKey,
            'proof_sha256' => $this->proofSha256,
            'proof_mime_type' => $this->proofMimeType,
            'proof_file_size' => $this->proofFileSize,
            'proof_deleted_at' => $this->proofDeletedAt,
            'external_reference' => $this->externalReference,
            'transaction_fingerprint' => $this->transactionFingerprint,
            'verified_amount' => $this->verifiedAmount,
            'verified_paid_at' => $this->verifiedPaidAt,
            'approval_idempotency_key_hash' => $this->approvalIdempotencyKeyHash,
            'reviewer_id' => $this->reviewerId,
            'claimed_at' => $this->claimedAt,
            'decision_code' => $this->decisionCode,
            'internal_note' => $this->internalNote,
            'user_message' => $this->userMessage,
            'lock_version' => $this->lockVersion,
            'submitted_at' => $this->submittedAt,
            'reviewed_at' => $this->reviewedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function withId(int $id): self
    {
        if ($id <= 0) {
            throw new RuntimeException('Submission id must be positive.');
        }

        return new self(
            id: $id,
            submissionKey: $this->submissionKey,
            orderId: $this->orderId,
            userId: $this->userId,
            state: $this->state,
            channel: $this->channel,
            accountKey: $this->accountKey,
            currency: $this->currency,
            expectedAmount: $this->expectedAmount,
            reportedAmount: $this->reportedAmount,
            reportedPaidAt: $this->reportedPaidAt,
            payerNote: $this->payerNote,
            proofStorageKey: $this->proofStorageKey,
            proofSha256: $this->proofSha256,
            proofMimeType: $this->proofMimeType,
            proofFileSize: $this->proofFileSize,
            proofDeletedAt: $this->proofDeletedAt,
            externalReference: $this->externalReference,
            transactionFingerprint: $this->transactionFingerprint,
            verifiedAmount: $this->verifiedAmount,
            verifiedPaidAt: $this->verifiedPaidAt,
            approvalIdempotencyKeyHash: $this->approvalIdempotencyKeyHash,
            reviewerId: $this->reviewerId,
            claimedAt: $this->claimedAt,
            decisionCode: $this->decisionCode,
            internalNote: $this->internalNote,
            userMessage: $this->userMessage,
            lockVersion: $this->lockVersion,
            submittedAt: $this->submittedAt,
            reviewedAt: $this->reviewedAt,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }

    public function withState(
        SubmissionState $state,
        ?string $reviewedAt = null,
        ?int $reviewerId = null,
        ?string $decisionCode = null,
        ?string $internalNote = null,
        ?string $userMessage = null,
    ): self {
        return new self(
            id: $this->id,
            submissionKey: $this->submissionKey,
            orderId: $this->orderId,
            userId: $this->userId,
            state: $state,
            channel: $this->channel,
            accountKey: $this->accountKey,
            currency: $this->currency,
            expectedAmount: $this->expectedAmount,
            reportedAmount: $this->reportedAmount,
            reportedPaidAt: $this->reportedPaidAt,
            payerNote: $this->payerNote,
            proofStorageKey: $this->proofStorageKey,
            proofSha256: $this->proofSha256,
            proofMimeType: $this->proofMimeType,
            proofFileSize: $this->proofFileSize,
            proofDeletedAt: $this->proofDeletedAt,
            externalReference: $this->externalReference,
            transactionFingerprint: $this->transactionFingerprint,
            verifiedAmount: $this->verifiedAmount,
            verifiedPaidAt: $this->verifiedPaidAt,
            approvalIdempotencyKeyHash: $this->approvalIdempotencyKeyHash,
            reviewerId: $reviewerId ?? $this->reviewerId,
            claimedAt: $this->claimedAt,
            decisionCode: $decisionCode ?? $this->decisionCode,
            internalNote: $internalNote ?? $this->internalNote,
            userMessage: $userMessage ?? $this->userMessage,
            lockVersion: $this->lockVersion + 1,
            submittedAt: $this->submittedAt,
            reviewedAt: $state->isTerminal() ? ($reviewedAt ?? $this->reviewedAt) : $this->reviewedAt,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }

    public function withVerification(
        string $transactionFingerprint,
        string $verifiedAmount,
        string $verifiedPaidAt,
    ): self {
        return new self(
            id: $this->id,
            submissionKey: $this->submissionKey,
            orderId: $this->orderId,
            userId: $this->userId,
            state: $this->state,
            channel: $this->channel,
            accountKey: $this->accountKey,
            currency: $this->currency,
            expectedAmount: $this->expectedAmount,
            reportedAmount: $this->reportedAmount,
            reportedPaidAt: $this->reportedPaidAt,
            payerNote: $this->payerNote,
            proofStorageKey: $this->proofStorageKey,
            proofSha256: $this->proofSha256,
            proofMimeType: $this->proofMimeType,
            proofFileSize: $this->proofFileSize,
            proofDeletedAt: $this->proofDeletedAt,
            externalReference: $this->externalReference,
            transactionFingerprint: $transactionFingerprint,
            verifiedAmount: $verifiedAmount,
            verifiedPaidAt: $verifiedPaidAt,
            approvalIdempotencyKeyHash: $this->approvalIdempotencyKeyHash,
            reviewerId: $this->reviewerId,
            claimedAt: $this->claimedAt,
            decisionCode: $this->decisionCode,
            internalNote: $this->internalNote,
            userMessage: $this->userMessage,
            lockVersion: $this->lockVersion,
            submittedAt: $this->submittedAt,
            reviewedAt: $this->reviewedAt,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }

    public function idempotencyHash(): string
    {
        $payload = $this->toArray();
        unset($payload['id'], $payload['created_at'], $payload['updated_at'], $payload['lock_version']);

        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function assertInvariant(): void
    {
        if ($this->id < 0) {
            throw new RuntimeException('Submission id must be non-negative.');
        }

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->submissionKey)) {
            throw new RuntimeException('submission_key must be a UUID v1-v5 string.');
        }

        if ($this->orderId <= 0 || $this->userId <= 0) {
            throw new RuntimeException('order_id and user_id must be positive.');
        }

        if ($this->currency === '') {
            throw new RuntimeException('currency is required.');
        }

        if (! preg_match('/^[A-Z]{3}$/', $this->currency)) {
            throw new RuntimeException('currency must be 3 uppercase letters.');
        }

        if ($this->channel === '') {
            throw new RuntimeException('channel is required.');
        }

        if (strlen($this->channel) > 32) {
            throw new RuntimeException('channel must be <= 32 characters.');
        }

        $this->assertMoney('expected_amount', $this->expectedAmount);
        $this->assertMoney('reported_amount', $this->reportedAmount);

        $this->assertDateTime('reported_paid_at', $this->reportedPaidAt);
        $this->assertDateTime('submitted_at', $this->submittedAt);
        $this->assertDateTime('created_at', $this->createdAt);
        $this->assertDateTime('updated_at', $this->updatedAt);

        if ($this->payerNote !== null && mb_strlen($this->payerNote) > 255) {
            throw new RuntimeException('payer_note exceeds 255 chars.');
        }

        $proofStorageKey = strtolower($this->proofStorageKey);
        if ($proofStorageKey === '') {
            throw new RuntimeException('proof_storage_key is required.');
        }

        if ($proofStorageKey === 'public' || str_contains($proofStorageKey, 'wp-content/uploads')) {
            throw new RuntimeException('proof_storage_key should avoid public media uploads directory.');
        }

        if (! preg_match('/^[a-f0-9]{64}$/i', $this->proofSha256)) {
            throw new RuntimeException('proof_sha256 must be SHA-256 hex digest.');
        }

        if (strlen($this->proofMimeType) === 0 || strlen($this->proofMimeType) > 128) {
            throw new RuntimeException('proof_mime_type is required and must be <=128 chars.');
        }

        if ($this->proofFileSize <= 0) {
            throw new RuntimeException('proof_file_size must be positive.');
        }

        if ($this->proofDeletedAt !== null) {
            $this->assertDateTime('proof_deleted_at', $this->proofDeletedAt);
        }

        if ($this->externalReference !== null && strlen($this->externalReference) > 128) {
            throw new RuntimeException('external_reference exceeds 128 chars.');
        }

        if ($this->transactionFingerprint !== null && ! preg_match('/^[a-f0-9]{64}$/i', $this->transactionFingerprint)) {
            throw new RuntimeException('transaction_fingerprint must be SHA-256 hex digest.');
        }

        if ($this->verifiedAmount !== null) {
            $this->assertMoney('verified_amount', $this->verifiedAmount);
        }

        if ($this->verifiedPaidAt !== null) {
            $this->assertDateTime('verified_paid_at', $this->verifiedPaidAt);
        }

        if ($this->approvalIdempotencyKeyHash !== null && ! preg_match('/^[a-f0-9]{64}$/i', $this->approvalIdempotencyKeyHash)) {
            throw new RuntimeException('approval_idempotency_key_hash must be SHA-256 hex digest.');
        }

        if ($this->reviewerId !== null && $this->reviewerId <= 0) {
            throw new RuntimeException('reviewer_id must be positive.');
        }

        if ($this->claimedAt !== null) {
            $this->assertDateTime('claimed_at', $this->claimedAt);
        }

        if ($this->decisionCode !== null && strlen($this->decisionCode) > 64) {
            throw new RuntimeException('decision_code exceeds 64 chars.');
        }

        if ($this->internalNote !== null && strlen($this->internalNote) > 2000) {
            throw new RuntimeException('internal_note exceeds 2000 chars.');
        }

        if ($this->userMessage !== null && strlen($this->userMessage) > 1000) {
            throw new RuntimeException('user_message exceeds 1000 chars.');
        }

        if ($this->reviewedAt !== null) {
            $this->assertDateTime('reviewed_at', $this->reviewedAt);
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string === '' ? null : $string;
    }

    private function assertMoney(string $name, string $amount): void
    {
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new RuntimeException($name.' must be a decimal with up to two places.');
        }

        if (function_exists('bccomp')) {
            if (bccomp($amount, '0', 2) < 0) {
                throw new RuntimeException($name.' must not be negative.');
            }

            return;
        }

        $parts = explode('.', $amount, 2);
        if (intval($parts[0] ?? 0) < 0) {
            throw new RuntimeException($name.' must not be negative.');
        }
    }

    private function assertDateTime(string $name, string $value): void
    {
        if ($value === '') {
            throw new RuntimeException($name.' is required.');
        }

        if (strtotime($value) === false) {
            throw new RuntimeException($name.' must be parseable datetime.');
        }
    }
}
