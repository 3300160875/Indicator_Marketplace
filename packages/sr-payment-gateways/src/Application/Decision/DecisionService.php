<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Application\Decision;

use StockResource\PaymentGateways\Submission\PaymentSubmission;
use StockResource\PaymentGateways\Submission\PaymentSubmissionException;
use StockResource\PaymentGateways\Submission\PaymentSubmissionRepository;
use StockResource\PaymentGateways\Submission\PaymentSubmissionStateMachine;
use RuntimeException;

final readonly class DecisionService
{
    private const array ALLOWED_DECISION_CODES = [
        'AMOUNT_MISMATCH',
        'DUPLICATE_TRANSACTION',
        'ORDER_EXPIRED',
        'RISK_REVIEW',
        'TRANSACTION_NOT_FOUND',
        'WRONG_ACCOUNT',
        'PROOF_UNREADABLE',
        'NEEDS_MORE_INFO',
        'TIMEOUT_EXPIRED',
    ];

    private mixed $nowProvider;

    /**
     * @param callable(int $reviewerId, PaymentSubmission $submission): bool $canDecide
     */
    public function __construct(
        private PaymentSubmissionRepository $repository,
        private PaymentSubmissionStateMachine $stateMachine,
        private mixed $canDecide,
        mixed $nowProvider = null,
    ) {
        if (! is_callable($this->canDecide)) {
            throw new RuntimeException('canDecide callback is required.');
        }

        $this->nowProvider = $nowProvider ?? static fn (): string => (new \DateTimeImmutable('now'))->format(\DATE_ATOM);
    }

    public function requestMoreInfo(
        int $submissionId,
        int $reviewerId,
        int $expectedLockVersion,
        string $decisionCode,
        ?string $internalNote = null,
        ?string $userMessage = null,
    ): PaymentSubmission {
        return $this->decide(
            submissionId: $submissionId,
            reviewerId: $reviewerId,
            expectedLockVersion: $expectedLockVersion,
            action: 'need_more_info',
            decisionCode: $decisionCode,
            defaultDecisionCode: 'NEEDS_MORE_INFO',
            internalNote: $internalNote,
            userMessage: $userMessage,
        );
    }

    public function reject(
        int $submissionId,
        int $reviewerId,
        int $expectedLockVersion,
        string $decisionCode,
        ?string $internalNote = null,
        ?string $userMessage = null,
    ): PaymentSubmission {
        return $this->decide(
            submissionId: $submissionId,
            reviewerId: $reviewerId,
            expectedLockVersion: $expectedLockVersion,
            action: 'reject',
            decisionCode: $decisionCode,
            defaultDecisionCode: 'RISK_REVIEW',
            internalNote: $internalNote,
            userMessage: $userMessage,
        );
    }

    /**
     * @return array<string>
     */
    public function allowedDecisionCodes(): array
    {
        return array_values(self::ALLOWED_DECISION_CODES);
    }

    private function decide(
        int $submissionId,
        int $reviewerId,
        int $expectedLockVersion,
        string $action,
        string $decisionCode,
        string $defaultDecisionCode,
        ?string $internalNote,
        ?string $userMessage,
    ): PaymentSubmission {
        $submission = $this->repository->findById($submissionId);
        if ($submission === null) {
            throw new PaymentDecisionException('submission_not_found', 'Submission not found.');
        }

        if (! ($this->canDecide)($reviewerId, $submission)) {
            throw new PaymentDecisionException('permission_denied', 'Reviewer has no permission to make decision.');
        }

        if ($expectedLockVersion < 0) {
            throw new PaymentDecisionException('lock_version_mismatch', 'Expected lock version must be non-negative.');
        }

        try {
            $next = $this->stateMachine->transition($submission, $action, $expectedLockVersion);
        } catch (PaymentSubmissionException $exception) {
            throw match ($exception->codeName) {
                'lock_version_mismatch' => new PaymentDecisionException('lock_version_mismatch', $exception->getMessage()),
                default => new PaymentDecisionException('state_conflict', $exception->getMessage()),
            };
        }

        $next = $this->withDecision(
            submission: $next,
            reviewerId: $reviewerId,
            decisionCode: self::normalizeDecisionCode($decisionCode, $defaultDecisionCode),
            internalNote: $internalNote,
            userMessage: $userMessage,
        );

        try {
            return $this->repository->update($next, $submission->lockVersion);
        } catch (PaymentSubmissionException $exception) {
            if ($exception->codeName === 'lock_version_mismatch') {
                throw new PaymentDecisionException('lock_version_mismatch', $exception->getMessage());
            }

            if ($exception->codeName === 'submission_not_found') {
                throw new PaymentDecisionException('submission_not_found', $exception->getMessage());
            }

            throw new PaymentDecisionException($exception->codeName, $exception->getMessage());
        } catch (\RuntimeException $exception) {
            throw new PaymentDecisionException('invalid_input', $exception->getMessage());
        }
    }

    private function withDecision(
        PaymentSubmission $submission,
        int $reviewerId,
        string $decisionCode,
        ?string $internalNote,
        ?string $userMessage,
    ): PaymentSubmission {
        return PaymentSubmission::fromArray(array_replace(
            $submission->toArray(),
            [
                'reviewer_id' => $reviewerId,
                'decision_code' => $decisionCode,
                'internal_note' => self::normalizeNullableString($internalNote),
                'user_message' => self::normalizeNullableString($userMessage),
                'reviewed_at' => $this->now(),
                'claimed_at' => null,
            ],
        ));
    }

    private static function normalizeDecisionCode(string $code, string $default): string
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '') {
            return $default;
        }

        if (! in_array($normalized, self::ALLOWED_DECISION_CODES, true)) {
            throw new PaymentDecisionException('invalid_decision_code', 'Unsupported decision code.');
        }

        return $normalized;
    }

    private static function normalizeNullableString(?string $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function now(): string
    {
        return (string) ($this->nowProvider)();
    }
}

final class PaymentDecisionException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }
}
