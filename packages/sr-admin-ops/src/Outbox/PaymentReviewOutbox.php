<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Outbox;

use RuntimeException;

final readonly class PaymentReviewOutbox
{
    public const EVENT_NAME = 'order.payment_reviewed';
    public const AGGREGATE_TYPE = 'payment_submission';

    public static function reviewedEvent(
        int $submissionId,
        int $orderId,
        int $userId,
        string $state,
        int $reviewerId,
        string $eventKey,
        string $now,
    ): OutboxEvent {
        if ($submissionId <= 0) {
            throw new RuntimeException('submission_id must be positive.');
        }

        if ($orderId <= 0) {
            throw new RuntimeException('order_id must be positive.');
        }

        if ($userId <= 0) {
            throw new RuntimeException('user_id must be positive.');
        }

        if ($reviewerId <= 0) {
            throw new RuntimeException('reviewer_id must be positive.');
        }

        $state = trim($state);
        if ($state === '') {
            throw new RuntimeException('state is required.');
        }

        return OutboxEvent::create(
            eventKey: $eventKey,
            eventName: self::EVENT_NAME,
            aggregateType: self::AGGREGATE_TYPE,
            aggregateId: (string) $submissionId,
            payload: [
                'submission_id' => $submissionId,
                'order_id' => $orderId,
                'user_id' => $userId,
                'state' => $state,
                'reviewer_id' => $reviewerId,
            ],
            now: $now,
        );
    }

    public static function defaultEventKey(int $submissionId, int $orderId): string
    {
        return 'order.payment_reviewed:submission:'.$submissionId.':order:'.$orderId;
    }
}

