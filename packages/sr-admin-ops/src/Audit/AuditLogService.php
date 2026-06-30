<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Audit;

final readonly class AuditLogService
{
    public function __construct(
        private AuditLogRepository $repository,
        private AuditActionCatalog $catalog,
        private AuditRedactor $redactor,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function record(
        string $action,
        string $actorType,
        int|string|null $actorId,
        string $subjectType,
        int|string $subjectId,
        string $requestId,
        string $occurredAt,
        array $metadata = [],
    ): AuditLogRecord {
        if (! $this->catalog->knows($action)) {
            throw new AuditException('unknown_audit_action', 'Audit action is not registered.');
        }

        return $this->repository->append(new AuditLogRecord(
            id: 0,
            action: $action,
            actorType: $actorType,
            actorId: $actorId,
            subjectType: $subjectType,
            subjectId: $subjectId,
            requestId: $requestId,
            occurredAt: $occurredAt,
            highRisk: $this->catalog->isHighRisk($action),
            metadata: $this->redactor->redact($metadata),
        ));
    }
}
