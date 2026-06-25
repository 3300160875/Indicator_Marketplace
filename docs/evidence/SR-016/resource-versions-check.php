<?php
declare(strict_types=1);

use StockResource\Core\Version\InMemoryResourceVersionRepository;
use StockResource\Core\Version\ResourceVersion;
use StockResource\Core\Version\ResourceVersionSchemaMigration;
use StockResource\Core\Version\ResourceVersionScanStatus;
use StockResource\Core\Version\ResourceVersionStatus;
use StockResource\Core\Version\ResourceVersionWorkflow;

$root = dirname(__DIR__, 3);
$files = [
    '/packages/sr-core/src/Infrastructure/Migration/Migration.php',
    '/packages/sr-core/src/Version/ResourceVersionStatus.php',
    '/packages/sr-core/src/Version/ResourceVersionScanStatus.php',
    '/packages/sr-core/src/Version/ResourceVersion.php',
    '/packages/sr-core/src/Version/ResourceVersionRepository.php',
    '/packages/sr-core/src/Version/InMemoryResourceVersionRepository.php',
    '/packages/sr-core/src/Version/ResourceVersionWorkflowStage.php',
    '/packages/sr-core/src/Version/ResourceVersionWorkflow.php',
    '/packages/sr-core/src/Version/ResourceVersionSchemaMigration.php',
];

foreach ($files as $file) {
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

function sr_expect_exception(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (RuntimeException) {
        return;
    }

    throw new RuntimeException($message);
}

$migration = ResourceVersionSchemaMigration::create();
$sql = $migration->sql('wp_');
sr_same('sr_resource_versions', $migration->tableName(), 'migration exposes stable table name');
sr_assert(str_contains($sql, 'CREATE TABLE wp_sr_resource_versions'), 'migration creates the prefixed resource versions table');
sr_assert(str_contains($sql, 'version_label VARCHAR(64) NOT NULL'), 'migration contains version label');
sr_assert(str_contains($sql, 'storage_provider VARCHAR(24) NULL'), 'migration contains storage provider');
sr_assert(str_contains($sql, 'storage_bucket VARCHAR(128) NULL'), 'migration contains storage bucket');
sr_assert(str_contains($sql, 'storage_key VARCHAR(512) NULL'), 'migration contains storage key');
sr_assert(str_contains($sql, 'sha256 CHAR(64) NULL'), 'migration contains sha256 digest');
sr_assert(str_contains($sql, 'compatibility_json LONGTEXT NULL'), 'migration contains compatibility json');
sr_assert(str_contains($sql, "scan_status VARCHAR(24) NOT NULL DEFAULT 'pending'"), 'migration contains scan status default');
sr_assert(str_contains($sql, 'approved_by BIGINT UNSIGNED NULL'), 'migration contains approver');
sr_assert(str_contains($sql, 'activated_at DATETIME NULL'), 'migration contains activation timestamp');
sr_assert(str_contains($sql, 'created_at DATETIME NOT NULL'), 'migration contains created timestamp');
sr_assert(str_contains($sql, 'updated_at DATETIME NOT NULL'), 'migration contains updated timestamp');
sr_assert(str_contains($sql, 'KEY idx_resource_status (resource_id, status)'), 'migration has resource status index');
sr_assert(str_contains($sql, 'KEY idx_resource_current (resource_id, is_current)'), 'migration has current lookup index');
sr_assert(str_contains($sql, 'KEY idx_sha256 (sha256)'), 'migration has sha256 lookup index');
sr_assert(preg_match('/^[a-f0-9]{64}$/', $migration->checksum()) === 1, 'migration checksum is a SHA-256 digest');

sr_same(
    ['draft', 'scanning', 'review', 'active', 'suspended', 'archived'],
    array_map(static fn(ResourceVersionStatus $status): string => $status->value, ResourceVersionStatus::cases()),
    'resource version statuses are stable',
);
sr_same(
    ['pending', 'queued', 'scanning', 'clean', 'infected', 'failed'],
    array_map(static fn(ResourceVersionScanStatus $status): string => $status->value, ResourceVersionScanStatus::cases()),
    'resource version scan statuses are stable',
);

$workflow = ResourceVersionWorkflow::defaults();
sr_assert($workflow->canTransition(ResourceVersionStatus::Draft, ResourceVersionStatus::Scanning), 'draft can move to scanning');
sr_assert($workflow->canTransition(ResourceVersionStatus::Scanning, ResourceVersionStatus::Review), 'scanning can move to review');
sr_assert($workflow->canTransition(ResourceVersionStatus::Review, ResourceVersionStatus::Active), 'review can move to active');
sr_assert(! $workflow->canTransition(ResourceVersionStatus::Draft, ResourceVersionStatus::Active), 'draft cannot skip directly to active');
sr_same(['upload', 'scan', 'review', 'activate'], array_keys($workflow->stages()), 'upload scan review activate stages are explicit');
sr_same(true, $workflow->stages()['scan']->retryable, 'scan stage is retryable');
sr_same(true, $workflow->stages()['activate']->usesTransactionLock, 'activation stage requires transaction lock');

$first = ResourceVersion::fromArray([
    'id' => 1,
    'resource_id' => 1001,
    'version_label' => '1.0.0',
    'status' => 'review',
    'is_current' => false,
    'storage_provider' => 'local_private',
    'storage_key' => 'resource/1001/v1.zip',
    'original_filename' => 'trend-v1.zip',
    'mime_type' => 'application/zip',
    'file_size' => 1024,
    'sha256' => str_repeat('a', 64),
    'compatibility' => ['platform' => 'tongdaxin'],
    'scan_status' => 'clean',
    'scan_result' => ['engine' => 'fixture'],
    'release_notes' => 'Initial release.',
    'created_by' => 501,
    'created_at' => '2026-06-25 10:00:00',
    'updated_at' => '2026-06-25 10:00:00',
]);
$second = ResourceVersion::fromArray([
    'id' => 2,
    'resource_id' => 1001,
    'version_label' => '1.1.0',
    'status' => 'review',
    'is_current' => false,
    'storage_provider' => 'local_private',
    'storage_key' => 'resource/1001/v2.zip',
    'original_filename' => 'trend-v2.zip',
    'mime_type' => 'application/zip',
    'file_size' => 2048,
    'sha256' => str_repeat('b', 64),
    'compatibility' => ['platform' => 'tongdaxin'],
    'scan_status' => 'clean',
    'scan_result' => ['engine' => 'fixture'],
    'release_notes' => 'Bug fixes.',
    'created_by' => 501,
    'created_at' => '2026-06-25 10:01:00',
    'updated_at' => '2026-06-25 10:01:00',
]);

$repository = new InMemoryResourceVersionRepository();
$repository->create($first);
$repository->create($second);
sr_expect_exception(static fn() => $repository->create($first), 'versions are immutable and cannot be overwritten');

$directCurrent = ResourceVersion::fromArray([
    'id' => 4,
    'resource_id' => 1002,
    'version_label' => '1.0.0',
    'status' => 'draft',
    'is_current' => true,
    'scan_status' => 'pending',
    'created_by' => 501,
    'created_at' => '2026-06-25 10:01:00',
    'updated_at' => '2026-06-25 10:01:00',
]);
sr_expect_exception(static fn() => $repository->create($directCurrent), 'new versions cannot bypass activation by setting current directly');

$failedScan = ResourceVersion::fromArray([
    'id' => 5,
    'resource_id' => 1002,
    'version_label' => '1.0.0',
    'status' => 'review',
    'scan_status' => 'failed',
    'created_by' => 501,
    'created_at' => '2026-06-25 10:01:00',
    'updated_at' => '2026-06-25 10:01:00',
]);
$repository->create($failedScan);
sr_expect_exception(static fn() => $repository->activateCurrent(1002, 5, 601, '2026-06-25 10:02:00'), 'non-clean scans cannot be activated');

$activatedSecond = $repository->activateCurrent(1001, 2, 601, '2026-06-25 10:02:00');
sr_same(2, $activatedSecond->id, 'activation returns the target version');
sr_same(ResourceVersionStatus::Active, $activatedSecond->status, 'activated version becomes active');
sr_same(true, $activatedSecond->isCurrent, 'activated version becomes current');
sr_same(601, $activatedSecond->approvedBy, 'activation records approver');
sr_same('2026-06-25 10:02:00', $activatedSecond->activatedAt, 'activation records timestamp');
sr_same(2, $repository->currentForResource(1001)?->id, 'repository exposes the current version');
sr_same(false, $repository->find(1)?->isCurrent, 'activation clears previous current flags');
sr_same([1002, 1001], $repository->transactionLockLog(), 'activation attempts use a resource transaction lock');

$activatedFirst = $repository->activateCurrent(1001, 1, 602, '2026-06-25 10:03:00');
sr_same(1, $activatedFirst->id, 'current can move to another version of the same resource');
sr_same(1, $repository->currentForResource(1001)?->id, 'only one current version remains after reactivation');
sr_same(false, $repository->find(2)?->isCurrent, 'reactivation clears the old current version');
sr_same([1002, 1001, 1001], $repository->transactionLockLog(), 'each activation attempt uses a transaction lock');

$currentForSameResource = ResourceVersion::fromArray([
    'id' => 3,
    'resource_id' => 1001,
    'version_label' => '2.0.0',
    'status' => 'active',
    'is_current' => true,
    'scan_status' => 'clean',
    'created_by' => 501,
    'created_at' => '2026-06-25 10:04:00',
    'updated_at' => '2026-06-25 10:04:00',
]);
sr_expect_exception(static fn() => $repository->create($currentForSameResource), 'repository rejects a second current version for one resource');

echo "SR-016 resource versions check: ok\n";
