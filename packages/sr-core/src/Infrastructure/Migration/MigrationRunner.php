<?php
declare(strict_types=1);

namespace StockResource\Core\Infrastructure\Migration;

use RuntimeException;
use Throwable;

final readonly class MigrationRunner
{
    public function __construct(private MigrationRepository $repository)
    {
    }

    /**
     * @param list<Migration> $migrations
     */
    public function migrate(array $migrations, bool $dryRun, ?string $toVersion = null): MigrationResult
    {
        $sorted = $this->sortAndValidate($migrations);
        if ($toVersion !== null) {
            $sorted = array_values(array_filter(
                $sorted,
                static fn(Migration $migration): bool => strcmp($migration->version(), $toVersion) <= 0,
            ));
        }

        $planned = [];
        $applied = [];
        $failures = [];
        $batchNo = $this->repository->nextBatchNo();

        foreach ($sorted as $migration) {
            $existing = $this->repository->find($migration->version());
            if ($existing !== null) {
                if (! hash_equals($existing->checksum, $migration->checksum())) {
                    throw new RuntimeException('Migration checksum mismatch for ' . $migration->version());
                }

                continue;
            }

            $planned[] = [
                'version' => $migration->version(),
                'checksum' => $migration->checksum(),
                'description' => $migration->description(),
            ];

            if ($dryRun) {
                continue;
            }

            $started = microtime(true);
            try {
                $migration->up();
                $executionMs = max(0, (int) round((microtime(true) - $started) * 1000));
                $this->repository->recordApplied(new MigrationRecord(
                    version: $migration->version(),
                    checksum: $migration->checksum(),
                    batchNo: $batchNo,
                    executionMs: $executionMs,
                    appliedAt: gmdate('Y-m-d H:i:s'),
                ));
                $applied[] = $migration->version();
            } catch (Throwable $exception) {
                $message = $exception->getMessage();
                $this->repository->recordFailure($migration->version(), $message);
                $failures[] = ['version' => $migration->version(), 'message' => $message];
                break;
            }
        }

        return new MigrationResult($planned, $applied, $failures);
    }

    /**
     * @param list<Migration> $migrations
     * @return list<Migration>
     */
    private function sortAndValidate(array $migrations): array
    {
        $byVersion = [];
        foreach ($migrations as $migration) {
            if (! preg_match('/^[0-9]{12,20}$/', $migration->version())) {
                throw new RuntimeException('Migration version must be a stable numeric timestamp.');
            }

            if (isset($byVersion[$migration->version()])) {
                throw new RuntimeException('Duplicate migration version: ' . $migration->version());
            }

            if (! preg_match('/^[a-f0-9]{64}$/', $migration->checksum())) {
                throw new RuntimeException('Migration checksum must be a SHA-256 hex digest.');
            }

            $byVersion[$migration->version()] = $migration;
        }

        ksort($byVersion);

        return array_values($byVersion);
    }
}
