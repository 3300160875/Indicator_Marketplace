<?php
declare(strict_types=1);

use StockResource\Core\Cli\MigrationCommand;
use StockResource\Core\Infrastructure\Migration\ArrayMigrationRepository;
use StockResource\Core\Infrastructure\Migration\Migration;
use StockResource\Core\Infrastructure\Migration\MigrationRunner;
use StockResource\Core\Infrastructure\Migration\SchemaMigrationDefinition;
use StockResource\Core\Infrastructure\Migration\TransactionSupportDetector;

$root = dirname(__DIR__, 3);
foreach ([
    '/packages/sr-core/src/Infrastructure/Migration/Migration.php',
    '/packages/sr-core/src/Infrastructure/Migration/MigrationRecord.php',
    '/packages/sr-core/src/Infrastructure/Migration/MigrationRepository.php',
    '/packages/sr-core/src/Infrastructure/Migration/ArrayMigrationRepository.php',
    '/packages/sr-core/src/Infrastructure/Migration/SchemaMigrationDefinition.php',
    '/packages/sr-core/src/Infrastructure/Migration/MigrationResult.php',
    '/packages/sr-core/src/Infrastructure/Migration/MigrationRunner.php',
    '/packages/sr-core/src/Infrastructure/Migration/TransactionSupportDetector.php',
    '/packages/sr-core/src/Cli/MigrationCommand.php',
] as $file) {
    require_once $root . $file;
}

function sr_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

final class RecordingMigration implements Migration
{
    /**
     * @param list<string> $calls
     */
    public function __construct(
        private string $version,
        private string $sql,
        private array &$calls,
        private bool $fail = false,
    ) {
    }

    public function version(): string
    {
        return $this->version;
    }

    public function description(): string
    {
        return 'recording migration';
    }

    public function checksum(): string
    {
        return hash('sha256', $this->sql);
    }

    public function up(): array
    {
        $this->calls[] = $this->version;
        if ($this->fail) {
            throw new RuntimeException('injected failure');
        }

        return [$this->sql];
    }
}

$calls = [];
$repository = new ArrayMigrationRepository();
$runner = new MigrationRunner($repository);

$first = new RecordingMigration('202606250001', 'CREATE TABLE {prefix}sr_schema_migrations (...);', $calls);
$second = new RecordingMigration('202606250002', 'CREATE TABLE {prefix}sr_idempotency_keys (...);', $calls);

$dryRun = $runner->migrate([$second, $first], dryRun: true);
sr_same(['202606250001', '202606250002'], array_column($dryRun->planned(), 'version'), 'dry-run sorts migrations by version');
sr_same([], $repository->appliedVersions(), 'dry-run does not record migrations');

$truncatedDryRun = $runner->migrate([$second, $first], dryRun: true, toVersion: '202606250001');
sr_same(['202606250001'], array_column($truncatedDryRun->planned(), 'version'), '--to truncates the migration plan');

$result = $runner->migrate([$second, $first], dryRun: false);
sr_same(['202606250001', '202606250002'], $repository->appliedVersions(), 'migrate records applied versions once');
sr_same(['202606250001', '202606250002'], $calls, 'migrate executes sorted migrations');
sr_same([], $result->failures(), 'successful migration has no failures');

$repeat = $runner->migrate([$first, $second], dryRun: false);
sr_same([], $repeat->applied(), 'repeat execution skips already applied migrations');

$changedFirst = new RecordingMigration('202606250001', 'CREATE TABLE changed (...);', $calls);
try {
    $runner->migrate([$changedFirst], dryRun: false);
    throw new RuntimeException('checksum mismatch was not detected');
} catch (RuntimeException $exception) {
    sr_assert(str_contains($exception->getMessage(), 'checksum'), 'checksum mismatch is rejected');
}

$failing = new RecordingMigration('202606250003', 'ALTER TABLE {prefix}sr_idempotency_keys ADD fail INT;', $calls, fail: true);
$failedResult = $runner->migrate([$failing], dryRun: false);
sr_same(['202606250003'], array_column($failedResult->failures(), 'version'), 'failure is recorded with the migration version');
sr_same(['202606250001', '202606250002'], $repository->appliedVersions(), 'failed migration is not marked applied');

$schema = SchemaMigrationDefinition::createTable();
sr_same('sr_schema_migrations', $schema->tableName(), 'schema migration table name is stable');
sr_assert(str_contains($schema->sql('{prefix}'), 'CREATE TABLE {prefix}sr_schema_migrations'), 'schema migration SQL uses dynamic prefix token');
sr_assert(strlen($schema->checksum()) === 64, 'schema migration checksum is sha256');

$mariaDbTransactions = new TransactionSupportDetector('InnoDB');
sr_assert(! $mariaDbTransactions->supportsTransactionalDdl(), 'MariaDB/InnoDB DDL is treated as non-transactional');
sr_assert($mariaDbTransactions->supportsTransactionalDml(), 'MariaDB/InnoDB DML supports transactions');

$command = new MigrationCommand(new MigrationRunner(new ArrayMigrationRepository()), [$first]);
sr_same(0, $command->migrate(['dry-run' => true]), 'WP-CLI migrate dry-run returns success');
sr_same(0, $command->status(), 'WP-CLI status returns success');
sr_same(0, $command->schemaVerify(), 'WP-CLI schema verify returns success');

echo "SR-011 migration framework check: ok\n";
