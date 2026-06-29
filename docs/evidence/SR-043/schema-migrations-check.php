<?php
declare(strict_types=1);

use StockResource\Core\Infrastructure\Migration\ArrayMigrationRepository;
use StockResource\Core\Infrastructure\Migration\MigrationRunner;
use StockResource\Entitlements\Infrastructure\Migration\DownloadEventsSchemaMigration;
use StockResource\Entitlements\Infrastructure\Migration\EntitlementCounterSchemaMigration;
use StockResource\Entitlements\Infrastructure\Migration\EntitlementSchemaMigration;

$root = dirname(__DIR__, 3);
$files = [
    '/packages/sr-core/src/Infrastructure/Migration/Migration.php',
    '/packages/sr-core/src/Infrastructure/Migration/MigrationRepository.php',
    '/packages/sr-core/src/Infrastructure/Migration/ArrayMigrationRepository.php',
    '/packages/sr-core/src/Infrastructure/Migration/MigrationRecord.php',
    '/packages/sr-core/src/Infrastructure/Migration/MigrationResult.php',
    '/packages/sr-core/src/Infrastructure/Migration/MigrationRunner.php',
    '/packages/sr-entitlements/src/Infrastructure/Migration/EntitlementSchemaMigration.php',
    '/packages/sr-entitlements/src/Infrastructure/Migration/EntitlementCounterSchemaMigration.php',
    '/packages/sr-entitlements/src/Infrastructure/Migration/DownloadEventsSchemaMigration.php',
];

foreach ($files as $file) {
    require_once $root . $file;
}

function sr043_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr043_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

function sr043_contains(string $haystack, string $needle, string $message): void
{
    sr043_assert(str_contains($haystack, $needle), $message . ' missing: '.$needle);
}

$entitlements = EntitlementSchemaMigration::create();
sr043_same('202606290200', $entitlements->version(), 'entitlement migration version');
sr043_same('sr_entitlements', $entitlements->tableName(), 'entitlement table name');
sr043_same(64, strlen($entitlements->checksum()), 'entitlement migration checksum length');
sr043_assert(preg_match('/^[0-9]{12,20}$/', $entitlements->version()) === 1, 'entitlement migration version format');
$entitlementSql = $entitlements->sql('wp_');
sr043_contains($entitlementSql, 'CREATE TABLE wp_sr_entitlements', 'entitlements migration creates table');
sr043_contains($entitlementSql, 'UNIQUE KEY uq_source_order_item (source_order_item_id)', 'entitlements migration includes source_order_item unique');
sr043_contains($entitlementSql, 'KEY idx_user_active (user_id, status, expires_at)', 'entitlements migration includes user_status index');
sr043_contains($entitlementSql, 'KEY idx_order (source_order_id)', 'entitlements migration includes order index');
sr043_contains($entitlementSql, 'KEY idx_parent (parent_entitlement_id)', 'entitlements migration includes parent index');
sr043_same(1, preg_match('/created_at DATETIME NOT NULL/', $entitlementSql), 'entitlements migration keeps created_at DATETIME');
sr043_same(1, preg_match('/updated_at DATETIME NOT NULL/', $entitlementSql), 'entitlements migration keeps updated_at DATETIME');

$counters = EntitlementCounterSchemaMigration::create();
sr043_same('202606290201', $counters->version(), 'entitlement counter migration version');
sr043_same('sr_entitlement_counters', $counters->tableName(), 'counter table name');
sr043_same(64, strlen($counters->checksum()), 'counter migration checksum length');
$counterSql = $counters->sql('wp_');
sr043_contains($counterSql, 'CREATE TABLE wp_sr_entitlement_counters', 'counter migration creates table');
sr043_contains($counterSql, 'UNIQUE KEY uq_counter_period (entitlement_id, period_type, period_key)', 'counter migration includes counter unique key');
sr043_contains($counterSql, 'KEY idx_user_period (user_id, period_type, period_key)', 'counter migration includes user period index');
sr043_contains($counterSql, 'limit_snapshot INT UNSIGNED NOT NULL', 'counter migration stores snapshot limit');

$events = DownloadEventsSchemaMigration::create();
sr043_same('202606290202', $events->version(), 'download events migration version');
sr043_same('sr_download_events', $events->tableName(), 'download events table name');
sr043_same(64, strlen($events->checksum()), 'download events migration checksum length');
$eventsSql = $events->sql('wp_');
sr043_contains($eventsSql, 'CREATE TABLE wp_sr_download_events', 'events migration creates table');
sr043_contains($eventsSql, 'UNIQUE KEY uq_event_request (request_id)', 'events migration includes request unique');
sr043_contains($eventsSql, 'UNIQUE KEY uq_event_token (token_id)', 'events migration includes token unique');
sr043_contains($eventsSql, 'KEY idx_user_date (user_id, created_at)', 'events migration includes user date index');
sr043_contains($eventsSql, 'KEY idx_resource_result (resource_id, result, created_at)', 'events migration includes resource result index');

sr043_assert(strpos($entitlementSql, 'DATETIME') !== false, 'entitlements table uses DATETIME fields');
sr043_assert(strpos($counterSql, 'DATETIME') !== false, 'counter table uses DATETIME fields');
sr043_assert(strpos($eventsSql, 'DATETIME') !== false, 'events table uses DATETIME fields');

$migrations = [
    $entitlements,
    $counters,
    $events,
];

$runner = new MigrationRunner(new ArrayMigrationRepository);
$dry = $runner->migrate($migrations, true);
sr043_same(3, count($dry->planned()), 'dry run plans all three migrations');
sr043_same([], $dry->applied(), 'dry run does not apply');
sr043_same([], $dry->failures(), 'dry run has no failures');

$run = $runner->migrate($migrations, false);
sr043_same(3, count($run->applied()), 'fresh migrate applies all three tables');
sr043_same([], $run->failures(), 'fresh migrate has no failures');

$again = $runner->migrate($migrations, false);
sr043_same([], $again->planned(), 'duplicate migrate skips all migrations');
sr043_same([], $again->applied(), 'duplicate migrate has no new applied rows');
sr043_same([], $again->failures(), 'duplicate migrate has no failures');

echo "SR-043 schema migration checks passed.\n";
