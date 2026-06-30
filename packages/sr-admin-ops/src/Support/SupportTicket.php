<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Support;

final readonly class SupportTicket
{
    private const TYPES = ['order', 'payment', 'resource', 'download', 'account', 'other'];
    private const STATUSES = ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'];
    private const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public function __construct(
        public int $id,
        public string $ticketNo,
        public int $userId,
        public string $type,
        public string $status,
        public string $priority,
        public string $subject,
        public ?int $orderId,
        public ?int $resourceId,
        public ?int $downloadEventId,
        public ?int $assigneeId,
        public ?string $firstResponseAt,
        public ?string $resolvedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
        if ($id < 0 || $userId <= 0) {
            throw new SupportException('invalid_ticket_id', 'Ticket and user IDs must be valid.');
        }
        if ($ticketNo === '' || $subject === '') {
            throw new SupportException('invalid_ticket_text', 'Ticket number and subject are required.');
        }
        if (! in_array($type, self::TYPES, true)) {
            throw new SupportException('invalid_ticket_type', 'Ticket type is unsupported.');
        }
        if ($orderId === null && $resourceId === null && $downloadEventId === null) {
            throw new SupportException('ticket_relation_required', 'Ticket must relate to an order, resource or download event.');
        }
        if (! in_array($status, self::STATUSES, true)) {
            throw new SupportException('invalid_ticket_status', 'Ticket status is unsupported.');
        }
        if (! in_array($priority, self::PRIORITIES, true)) {
            throw new SupportException('invalid_ticket_priority', 'Ticket priority is unsupported.');
        }
        foreach ([
            'first_response_at' => $firstResponseAt,
            'resolved_at' => $resolvedAt,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ] as $field => $value) {
            if ($value !== null && date_create_immutable($value) === false) {
                throw new SupportException('invalid_'.$field, $field.' must be an ISO-8601 datetime.');
            }
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: max(0, (int) ($data['id'] ?? 0)),
            ticketNo: trim((string) ($data['ticket_no'] ?? '')),
            userId: (int) ($data['user_id'] ?? 0),
            type: trim((string) ($data['type'] ?? 'other')),
            status: trim((string) ($data['status'] ?? 'open')),
            priority: trim((string) ($data['priority'] ?? 'normal')),
            subject: trim((string) ($data['subject'] ?? '')),
            orderId: self::nullablePositiveInt($data['order_id'] ?? null),
            resourceId: self::nullablePositiveInt($data['resource_id'] ?? null),
            downloadEventId: self::nullablePositiveInt($data['download_event_id'] ?? null),
            assigneeId: self::nullablePositiveInt($data['assignee_id'] ?? null),
            firstResponseAt: self::nullableString($data['first_response_at'] ?? null),
            resolvedAt: self::nullableString($data['resolved_at'] ?? null),
            createdAt: trim((string) ($data['created_at'] ?? '')),
            updatedAt: trim((string) ($data['updated_at'] ?? '')),
        );
    }

    public function withStatus(string $status, string $nowUtc): self
    {
        return new self(
            id: $this->id,
            ticketNo: $this->ticketNo,
            userId: $this->userId,
            type: $this->type,
            status: $status,
            priority: $this->priority,
            subject: $this->subject,
            orderId: $this->orderId,
            resourceId: $this->resourceId,
            downloadEventId: $this->downloadEventId,
            assigneeId: $this->assigneeId,
            firstResponseAt: $this->firstResponseAt,
            resolvedAt: in_array($status, ['resolved', 'closed'], true) ? $nowUtc : $this->resolvedAt,
            createdAt: $this->createdAt,
            updatedAt: $nowUtc,
        );
    }

    private static function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
