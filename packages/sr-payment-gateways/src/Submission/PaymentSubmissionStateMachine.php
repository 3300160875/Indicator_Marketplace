<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Submission;

final readonly class PaymentSubmissionStateMachine
{
    /**
     * @var array<string, array<string, string>>
     */
    private const TRANSITIONS = [
        'submitted' => [
            'claim_review' => 'under_review',
            'cancel' => 'cancelled',
        ],
        'under_review' => [
            'approve' => 'approved',
            'need_more_info' => 'needs_more_info',
            'reject' => 'rejected',
            'submit' => 'submitted',
        ],
        'needs_more_info' => [
            'submit' => 'submitted',
            'cancel' => 'cancelled',
        ],
        'approved' => [],
        'rejected' => [],
        'cancelled' => [],
    ];

    public function transition(PaymentSubmission $submission, string $action, int $expectedLockVersion): PaymentSubmission
    {
        if ($submission->lockVersion !== $expectedLockVersion) {
            throw PaymentSubmissionException::lockVersionMismatch($expectedLockVersion, $submission->lockVersion);
        }

        $nextState = self::TRANSITIONS[$submission->state->value][$action] ?? null;
        if ($nextState === null) {
            throw PaymentSubmissionException::invalidTransition($submission->state->value, $action);
        }

        $state = SubmissionState::from($nextState);

        return $submission->withState($state);
    }
}
