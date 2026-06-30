<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Audit;

interface AuditLogRepository
{
    public function append(AuditLogRecord $record): AuditLogRecord;

    /**
     * @return list<AuditLogRecord>
     */
    public function query(AuditLogQuery $query): array;
}
