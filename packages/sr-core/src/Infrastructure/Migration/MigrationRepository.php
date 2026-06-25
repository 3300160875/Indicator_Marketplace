<?php
declare(strict_types=1);

namespace StockResource\Core\Infrastructure\Migration;

interface MigrationRepository
{
    public function find(string $version): ?MigrationRecord;

    /**
     * @return list<string>
     */
    public function appliedVersions(): array;

    public function nextBatchNo(): int;

    public function recordApplied(MigrationRecord $record): void;

    public function recordFailure(string $version, string $message): void;

    /**
     * @return list<array{version: string, message: string}>
     */
    public function failures(): array;
}
