<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Support;

final readonly class SupportAuditEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $eventName,
        public int $ticketId,
        public string $requestId,
        public string $occurredAt,
        public int $actorId,
        public array $payload,
    ) {
        if ($eventName === '' || $ticketId < 0 || $actorId <= 0) {
            throw new SupportException('invalid_support_audit_event', 'Support audit event is invalid.');
        }
        self::assertUuid($requestId);
        self::assertDateTime($occurredAt);
    }

    private static function assertUuid(string $value): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) !== 1) {
            throw new SupportException('invalid_request_id', 'request_id must be a UUID.');
        }
    }

    private static function assertDateTime(string $value): void
    {
        if (date_create_immutable($value) === false) {
            throw new SupportException('invalid_occurred_at', 'occurred_at must be an ISO-8601 datetime.');
        }
    }
}
