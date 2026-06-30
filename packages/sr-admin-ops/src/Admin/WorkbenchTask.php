<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Admin;

final readonly class WorkbenchTask
{
    private const QUEUES = ['payment', 'membership', 'download', 'rights'];
    private const PRIORITIES = ['urgent', 'high', 'normal', 'low'];

    /**
     * @param array<string, mixed> $fields
     * @param list<string> $allowedActions
     */
    public function __construct(
        public string $id,
        public string $queue,
        public string $label,
        public string $priority,
        public string $status,
        public string $createdAt,
        public ?int $subjectUserId,
        public ?int $subjectResourceId,
        public ?int $assigneeId,
        public array $fields,
        public array $allowedActions,
        public string $source,
    ) {
        if ($id === '' || $label === '' || $status === '' || $source === '') {
            throw new AdminWorkbenchException('invalid_task_text', 'Workbench task text fields are required.');
        }
        if (! in_array($queue, self::QUEUES, true)) {
            throw new AdminWorkbenchException('invalid_queue', 'Workbench queue is unsupported.');
        }
        if (! in_array($priority, self::PRIORITIES, true)) {
            throw new AdminWorkbenchException('invalid_priority', 'Workbench priority is unsupported.');
        }
        if (date_create_immutable($createdAt) === false) {
            throw new AdminWorkbenchException('invalid_created_at', 'Workbench task created_at must be an ISO-8601 datetime.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: trim((string) ($data['id'] ?? '')),
            queue: trim((string) ($data['queue'] ?? '')),
            label: trim((string) ($data['label'] ?? '')),
            priority: trim((string) ($data['priority'] ?? 'normal')),
            status: trim((string) ($data['status'] ?? 'pending')),
            createdAt: trim((string) ($data['created_at'] ?? '')),
            subjectUserId: self::nullablePositiveInt($data['subject_user_id'] ?? null),
            subjectResourceId: self::nullablePositiveInt($data['subject_resource_id'] ?? null),
            assigneeId: self::nullablePositiveInt($data['assignee_id'] ?? null),
            fields: is_array($data['fields'] ?? null) ? $data['fields'] : [],
            allowedActions: array_values(array_map('strval', is_array($data['allowed_actions'] ?? null) ? $data['allowed_actions'] : [])),
            source: trim((string) ($data['source'] ?? '')),
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
}
