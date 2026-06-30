<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Rights;

final readonly class RightsAuditEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $eventName,
        public string $aggregateType,
        public string $aggregateId,
        public string $requestId,
        public string $occurredAt,
        public int $actorId,
        public array $payload,
    ) {
        if ($eventName === '') {
            throw new RightsException('invalid_audit_event', 'Audit event name is required.');
        }
        if ($aggregateType === '' || $aggregateId === '') {
            throw new RightsException('invalid_audit_subject', 'Audit aggregate is required.');
        }
        self::assertUuid($requestId, 'request_id');
        self::assertDateTime($occurredAt, 'occurred_at');
    }

    private static function assertUuid(string $value, string $field): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) !== 1) {
            throw new RightsException('invalid_'.$field, $field.' must be a UUID.');
        }
    }

    private static function assertDateTime(string $value, string $field): void
    {
        if (date_create_immutable($value) === false) {
            throw new RightsException('invalid_'.$field, $field.' must be an ISO-8601 datetime.');
        }
    }
}
