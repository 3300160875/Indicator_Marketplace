<?php
declare(strict_types=1);

namespace StockResource\Core\Cli;

use StockResource\Core\Infrastructure\Migration\Migration;
use StockResource\Core\Infrastructure\Migration\MigrationRunner;

final readonly class MigrationCommand
{
    /**
     * @param list<Migration> $migrations
     */
    public function __construct(
        private MigrationRunner $runner,
        private array $migrations,
    ) {
    }

    /**
     * @param array<string, mixed> $args
     */
    public function migrate(array $args = []): int
    {
        $dryRun = (bool) ($args['dry-run'] ?? false);
        $toVersion = isset($args['to']) ? (string) $args['to'] : null;
        $result = $this->runner->migrate($this->migrations, $dryRun, $toVersion);

        return $result->failures() === [] ? 0 : 1;
    }

    public function status(): int
    {
        $this->runner->migrate($this->migrations, dryRun: true);

        return 0;
    }

    public function schemaVerify(): int
    {
        $this->runner->migrate($this->migrations, dryRun: true);

        return 0;
    }
}
