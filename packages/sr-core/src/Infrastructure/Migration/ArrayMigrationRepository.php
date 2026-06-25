<?php
declare(strict_types=1);

namespace StockResource\Core\Infrastructure\Migration;

final class ArrayMigrationRepository implements MigrationRepository
{
    /** @var array<string, MigrationRecord> */
    private array $records = [];

    /** @var list<array{version: string, message: string}> */
    private array $failures = [];

    public function find(string $version): ?MigrationRecord
    {
        return $this->records[$version] ?? null;
    }

    public function appliedVersions(): array
    {
        $versions = array_map(
            static fn(MigrationRecord $record): string => $record->version,
            array_values($this->records),
        );
        sort($versions);

        return $versions;
    }

    public function nextBatchNo(): int
    {
        if ($this->records === []) {
            return 1;
        }

        return max(array_map(static fn(MigrationRecord $record): int => $record->batchNo, $this->records)) + 1;
    }

    public function recordApplied(MigrationRecord $record): void
    {
        if (isset($this->records[$record->version])) {
            return;
        }

        $this->records[$record->version] = $record;
    }

    public function recordFailure(string $version, string $message): void
    {
        $this->failures[] = ['version' => $version, 'message' => $message];
    }

    public function failures(): array
    {
        return $this->failures;
    }
}
