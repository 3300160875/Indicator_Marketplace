<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Audit;

final readonly class AuditLogRecord
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public int $id,
        public string $action,
        public string $actorType,
        public int|string|null $actorId,
        public string $subjectType,
        public int|string $subjectId,
        public string $requestId,
        public string $occurredAt,
        public bool $highRisk,
        public array $metadata,
    ) {
        if ($id < 0) {
            throw new AuditException('invalid_audit_id', 'Audit ID must be non-negative.');
        }
        foreach ([
            'action' => $action,
            'actor_type' => $actorType,
            'subject_type' => $subjectType,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new AuditException('invalid_'.$field, $field.' is required.');
            }
        }
        if ((string) $subjectId === '') {
            throw new AuditException('invalid_subject_id', 'subject_id is required.');
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $requestId) !== 1) {
            throw new AuditException('invalid_request_id', 'request_id must be a UUID.');
        }
        if (date_create_immutable($occurredAt) === false) {
            throw new AuditException('invalid_occurred_at', 'occurred_at must be an ISO-8601 datetime.');
        }
    }

    public function withId(int $id): self
    {
        return new self(
            id: $id,
            action: $this->action,
            actorType: $this->actorType,
            actorId: $this->actorId,
            subjectType: $this->subjectType,
            subjectId: $this->subjectId,
            requestId: $this->requestId,
            occurredAt: $this->occurredAt,
            highRisk: $this->highRisk,
            metadata: $this->metadata,
        );
    }
}
