<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Audit;

final readonly class AuditQueryView
{
    /**
     * @param list<AuditLogRecord> $records
     * @return array<string, mixed>
     */
    public static function page(array $records, AuditLogQuery $query): array
    {
        return [
            'data' => array_map(self::row(...), $records),
            'request_id' => $query->requestId,
            'limit' => $query->limit,
            'count' => count($records),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(AuditLogRecord $record): array
    {
        return [
            'id' => $record->id,
            'action' => $record->action,
            'actor_type' => $record->actorType,
            'actor_id' => $record->actorId,
            'subject_type' => $record->subjectType,
            'subject_id' => $record->subjectId,
            'request_id' => $record->requestId,
            'occurred_at' => $record->occurredAt,
            'high_risk' => $record->highRisk,
            'metadata' => $record->metadata,
        ];
    }
}
