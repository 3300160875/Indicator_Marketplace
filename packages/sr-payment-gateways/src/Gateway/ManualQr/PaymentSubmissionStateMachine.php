<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Gateway\ManualQr;

final readonly class PaymentSubmissionStateMachine
{
    private const TRANSITIONS = [
        'draft' => ['submit' => 'submitted', 'expire' => 'expired'],
        'submitted' => ['claim_review' => 'under_review', 'expire' => 'expired'],
        'under_review' => ['need_more_info' => 'need_more_info', 'reject' => 'rejected'],
        'need_more_info' => ['submit' => 'submitted', 'expire' => 'expired'],
        'rejected' => ['submit' => 'submitted', 'close' => 'closed'],
    ];

    public function transition(PaymentSubmission $submission, string $action, int $expectedLockVersion): PaymentSubmission
    {
        if ($submission->lockVersion !== $expectedLockVersion) {
            throw ManualQrException::lockVersionMismatch($expectedLockVersion, $submission->lockVersion);
        }

        $nextState = self::TRANSITIONS[$submission->state][$action] ?? null;
        if ($nextState === null) {
            throw ManualQrException::invalidTransition($submission->state, $action);
        }

        return $submission->withState($nextState);
    }
}
