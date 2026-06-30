<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Audit;

final class InMemoryAuditLogRepository implements AuditLogRepository
{
    /** @var list<AuditLogRecord> */
    private array $records = [];
    private int $nextId = 1;

    public function append(AuditLogRecord $record): AuditLogRecord
    {
        $stored = $record->withId($this->nextId++);
        $this->records[] = $stored;

        return $stored;
    }

    public function query(AuditLogQuery $query): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (AuditLogRecord $record): bool => $query->matches($record),
        ));
    }

    /**
     * @return list<AuditLogRecord>
     */
    public function all(): array
    {
        return $this->records;
    }

    public function count(): int
    {
        return count($this->records);
    }
}
