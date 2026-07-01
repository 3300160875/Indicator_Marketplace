<?php

declare(strict_types=1);

use StockResource\Contracts\Entitlement\AccessDecision;
use StockResource\Contracts\Entitlement\AccessDecisionContext;
use StockResource\Core\Support\Http\RequestIdFactory;
use StockResource\Core\Support\Logging\InMemoryLogSink;
use StockResource\Core\Support\Logging\SensitiveFieldRedactor;
use StockResource\Core\Support\Logging\StructuredLogger;
use StockResource\Entitlements\Application\QuotaCounterRecord;
use StockResource\Entitlements\Application\QuotaCounterStore;
use StockResource\Entitlements\Application\QuotaOperationResult;
use StockResource\Entitlements\Application\QuotaReservationRecord;
use StockResource\Entitlements\Application\QuotaService;
use StockResource\Entitlements\Infrastructure\Repository\Entitlement;
use StockResource\Entitlements\Infrastructure\Repository\InMemoryEntitlementRepository;
use StockResource\PrivateDownloads\Storage\Adapters\CurlHttpTransport;
use StockResource\PrivateDownloads\Storage\Adapters\MinioStorageAdapter;
use StockResource\PrivateDownloads\Storage\PutObjectOptions;
use StockResource\PrivateDownloads\Storage\StorageObjectKey;
use StockResource\PrivateDownloads\Token\DownloadTokenIssueRequest;
use StockResource\PrivateDownloads\Token\DownloadTokenService;
use StockResource\PrivateDownloads\Token\FixedTokenBytes;
use StockResource\PrivateDownloads\Token\InMemoryDownloadTokenRepository;

/**
 * SR-065 cross-plugin integration fixture.
 *
 * The fixture intentionally lives under tests/integration because SR-065 only
 * allows test files. It verifies the real Docker/WordPress/EDD/MinIO topology,
 * installs the SR schema into an isolated MariaDB database, and drives the
 * implemented package services for entitlement, quota, token and logging
 * decisions. It does not modify application runtime files.
 */

function sr065_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr065_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr065_root(): string
{
    return dirname(__DIR__, 2);
}

/**
 * @return array<string, mixed>
 */
function sr065_run_full_chain_fixture(): array
{
    $root = sr065_root();
    sr065_require_runtime_classes($root);

    $topology = sr065_assert_runtime_topology($root);
    $install = sr065_install_empty_database($root);
    $minio = sr065_assert_minio_object_chain($root);

    $forward = sr065_run_scenarios(sr065_service_fixtures(), ['free', 'single_purchase', 'vip', 'excluded', 'quota_exhausted', 'refund', 'unpublished']);
    $reverse = sr065_run_scenarios(sr065_service_fixtures(), ['unpublished', 'refund', 'quota_exhausted', 'excluded', 'vip', 'single_purchase', 'free']);
    sr065_same($forward, $reverse, 'scenario results must not depend on execution order');

    $observability = sr065_observability($forward);

    return [
        'status' => 'ok',
        'topology' => $topology,
        'install' => $install,
        'minio' => $minio,
        'scenarios' => $forward,
        'order_independence' => [
            'forward' => $forward,
            'reverse' => $reverse,
        ],
        'observability' => $observability,
    ];
}

function sr065_require_runtime_classes(string $root): void
{
    $files = [
        'packages/sr-contracts/src/Entitlement/AccessDecision.php',
        'packages/sr-contracts/src/Entitlement/AccessDecisionContext.php',
        'packages/sr-core/src/Support/Http/RequestIdFactory.php',
        'packages/sr-core/src/Support/Logging/InMemoryLogSink.php',
        'packages/sr-core/src/Support/Logging/SensitiveFieldRedactor.php',
        'packages/sr-core/src/Support/Logging/StructuredLogger.php',
        'packages/sr-entitlements/src/Infrastructure/Repository/EntitlementStatus.php',
        'packages/sr-entitlements/src/Infrastructure/Repository/EntitlementException.php',
        'packages/sr-entitlements/src/Infrastructure/Repository/Entitlement.php',
        'packages/sr-entitlements/src/Infrastructure/Repository/EntitlementRepository.php',
        'packages/sr-entitlements/src/Infrastructure/Repository/InMemoryEntitlementRepository.php',
        'packages/sr-entitlements/src/Application/EntitlementService.php',
        'packages/sr-entitlements/src/Application/QuotaService.php',
        'packages/sr-private-downloads/src/Storage/StorageException.php',
        'packages/sr-private-downloads/src/Storage/StorageObjectKey.php',
        'packages/sr-private-downloads/src/Storage/PutObjectOptions.php',
        'packages/sr-private-downloads/src/Storage/StoredObject.php',
        'packages/sr-private-downloads/src/Storage/SignedUrl.php',
        'packages/sr-private-downloads/src/Storage/StorageService.php',
        'packages/sr-private-downloads/src/Storage/Adapters/HttpTransport.php',
        'packages/sr-private-downloads/src/Storage/Adapters/CurlHttpTransport.php',
        'packages/sr-private-downloads/src/Storage/Adapters/S3SignatureV4Signer.php',
        'packages/sr-private-downloads/src/Storage/Adapters/MinioStorageAdapter.php',
        'packages/sr-private-downloads/src/Token/DownloadTokenService.php',
    ];

    foreach ($files as $file) {
        require_once $root.'/'.$file;
    }
}

/**
 * @return array<string, mixed>
 */
function sr065_assert_runtime_topology(string $root): array
{
    sr065_command_succeeds(['docker', 'compose', 'config', '--quiet'], $root, 'docker compose config is valid');

    $compose = file_get_contents($root.'/docker-compose.yml');
    sr065_assert($compose !== false, 'docker-compose.yml is readable');
    foreach (['nginx:', 'php:', 'cli:', 'mariadb:', 'redis:', 'minio:', 'minio-init:', 'mailpit:'] as $service) {
        sr065_assert(str_contains($compose, $service), 'runtime topology includes '.$service);
    }

    $phpProbe = sr065_command_output([
        'docker',
        'compose',
        'exec',
        '-T',
        'php',
        'php',
        '-r',
        'echo PHP_VERSION.PHP_EOL; echo file_exists("/var/www/html/web/wp/wp-load.php") ? "wp=yes\n" : "wp=no\n"; echo file_exists("/var/www/html/web/app/plugins/easy-digital-downloads/easy-digital-downloads.php") ? "edd=yes\n" : "edd=no\n";',
    ], $root, null, 'php container WordPress/EDD probe');
    sr065_assert(str_contains($phpProbe['stdout'], 'wp=yes'), 'WordPress core exists in php container');
    sr065_assert(str_contains($phpProbe['stdout'], 'edd=yes'), 'EDD plugin exists in php container');

    $activePlugins = sr065_live_active_plugins($root);
    sr065_assert(in_array('easy-digital-downloads/easy-digital-downloads.php', $activePlugins, true), 'EDD is active in the live WordPress database');

    $plugins = [
        'sr-core' => 'packages/sr-core/sr-core.php',
        'sr-entitlements' => 'packages/sr-entitlements/sr-entitlements.php',
        'sr-payment-gateways' => 'packages/sr-payment-gateways/sr-payment-gateways.php',
        'sr-private-downloads' => 'packages/sr-private-downloads/sr-private-downloads.php',
        'sr-admin-ops' => 'packages/sr-admin-ops/sr-admin-ops.php',
    ];
    foreach ($plugins as $slug => $path) {
        $entry = file_get_contents($root.'/'.$path);
        sr065_assert($entry !== false, 'plugin entry is readable: '.$slug);
        sr065_assert(str_contains($entry, 'Requires Plugins: easy-digital-downloads'), $slug.' declares EDD dependency');
        sr065_assert(str_contains($entry, 'Requires PHP: 8.3'), $slug.' declares PHP version');
    }

    return [
        'services' => ['nginx', 'php', 'cli', 'mariadb', 'redis', 'minio', 'minio-init', 'mailpit'],
        'docker_compose_config_valid' => true,
        'wordpress_core' => true,
        'edd_plugin_present' => true,
        'edd_active' => true,
        'plugins' => array_keys($plugins),
        'minio_bucket' => sr065_env($root)['S3_BUCKET'] ?? 'indicator-assets',
    ];
}

/**
 * @return list<string>
 */
function sr065_live_active_plugins(string $root): array
{
    $env = sr065_env($root);
    $mysqli = sr065_mysqli($env, $env['DB_NAME'] ?? 'indicator_marketplace');
    try {
        $prefix = $env['DB_PREFIX'] ?? 'wp_';
        $table = '`'.str_replace('`', '``', $prefix).'options`';
        $result = $mysqli->query('SELECT option_value FROM '.$table.' WHERE option_name = "active_plugins" LIMIT 1');
        sr065_assert($result instanceof mysqli_result, 'active plugins query succeeds');
        $row = $result->fetch_assoc();
        $value = is_array($row) ? (string) $row['option_value'] : '';
        $plugins = @unserialize($value);

        return is_array($plugins) ? array_values(array_map('strval', $plugins)) : [];
    } finally {
        $mysqli->close();
    }
}

/**
 * @return array<string, mixed>
 */
function sr065_install_empty_database(string $root): array
{
    $env = sr065_env($root);
    $database = 'sr065_'.getmypid().'_'.bin2hex(random_bytes(3));
    $schema = file_get_contents($root.'/docs/contracts/schema.sql');
    sr065_assert($schema !== false, 'schema contract is readable');

    $mysqli = sr065_mysqli($env);
    try {
        sr065_mysqli_exec($mysqli, 'CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $mysqli->select_db($database);
        sr065_mysqli_multi_exec($mysqli, $schema);

        $requiredTables = [
            'wp_sr_entitlements',
            'wp_sr_entitlement_counters',
            'wp_sr_download_tokens',
            'wp_sr_download_events',
            'wp_sr_rights_records',
            'wp_sr_audit_logs',
        ];
        foreach ($requiredTables as $table) {
            $result = $mysqli->query('SHOW TABLES LIKE "'.$mysqli->real_escape_string($table).'"');
            sr065_assert($result instanceof mysqli_result && $result->num_rows === 1, 'installed table exists: '.$table);
        }

        $now = '2026-07-01 00:00:00';
        sr065_mysqli_exec($mysqli, "INSERT INTO wp_sr_entitlements (user_id, grant_type, source_type, source_order_id, source_order_item_id, plan_download_id, resource_id, status, starts_at, scope_type, scope_snapshot_json, quota_snapshot_json, rules_version, priority, created_at, updated_at) VALUES (77, 'purchase', 'order_item', 5002, 6002, NULL, 1002, 'active', '$now', 'resource', '{\"type\":\"resources\",\"resource_ids\":[1002]}', NULL, 'sr065', 100, '$now', '$now')");
        sr065_mysqli_exec($mysqli, "INSERT INTO wp_sr_entitlement_counters (entitlement_id, user_id, period_type, period_key, limit_snapshot, used_count, reserved_count, created_at, updated_at) VALUES (1, 77, 'month', '2026-07', 2, 1, 0, '$now', '$now')");
        sr065_mysqli_exec($mysqli, "INSERT INTO wp_sr_download_tokens (request_id, token_hash, user_id, resource_id, version_id, grant_type, entitlement_id, counter_id, quota_units, status, expires_at, created_at, updated_at) VALUES ('11111111-1111-4111-8111-111111111111', REPEAT('a', 64), 77, 1002, 9002, 'purchase', 1, 1, 1, 'issued', '2026-07-01 00:10:00', '$now', '$now')");
        sr065_mysqli_exec($mysqli, "INSERT INTO wp_sr_download_events (request_id, token_id, user_id, entitlement_id, resource_id, version_id, access_source, counted, result, started_at, redirected_at, created_at, updated_at) VALUES ('22222222-2222-4222-8222-222222222222', 1, 77, 1, 1002, 9002, 'PURCHASE', 1, 'redirected', '$now', '$now', '$now', '$now')");
        sr065_mysqli_exec($mysqli, "INSERT INTO wp_sr_rights_records (resource_id, status, source_type, rights_holder, created_at, updated_at) VALUES (1002, 'approved', 'vendor', 'SR-065 fixture', '$now', '$now')");
        sr065_mysqli_exec($mysqli, "INSERT INTO wp_sr_audit_logs (actor_type, actor_id, action, object_type, object_id, request_id, created_at) VALUES ('system', 0, 'sr065.fixture', 'resource', '1002', '33333333-3333-4333-8333-333333333333', '$now')");

        $rows = [];
        foreach ($requiredTables as $table) {
            $count = sr065_scalar_int($mysqli, 'SELECT COUNT(*) FROM '.$table);
            $rows[$table] = $count;
        }
        sr065_assert($rows['wp_sr_entitlements'] === 1, 'fixture entitlement row inserted');
        sr065_assert($rows['wp_sr_download_events'] === 1, 'fixture download event row inserted');

        return [
            'empty_database_install' => 'ok',
            'database' => $database,
            'schema_source' => 'docs/contracts/schema.sql',
            'tables' => $requiredTables,
            'database_rows' => $rows,
        ];
    } finally {
        $mysqli->select_db('mysql');
        $mysqli->query('DROP DATABASE IF EXISTS `'.$database.'`');
        $mysqli->close();
    }
}

/**
 * @return array<string, mixed>
 */
function sr065_assert_minio_object_chain(string $root): array
{
    $env = sr065_env($root);
    sr065_command_succeeds(['docker', 'compose', 'run', '--rm', 'minio-init'], $root, 'minio bucket init');

    $endpoint = 'http://127.0.0.1:'.($env['MINIO_API_PORT'] ?? '9002');
    $bucket = $env['S3_BUCKET'] ?? 'indicator-assets';
    $adapter = new MinioStorageAdapter(
        endpoint: $endpoint,
        region: $env['S3_REGION'] ?? 'us-east-1',
        bucket: $bucket,
        accessKey: $env['MINIO_ROOT_USER'] ?? 'minioadmin-local',
        secretKey: $env['MINIO_ROOT_PASSWORD'] ?? 'minioadmin-local',
        transport: new CurlHttpTransport(),
    );

    $key = StorageObjectKey::fromString('sr065/fixture.txt');
    $stored = $adapter->put($key, 'sr065 fixture payload', PutObjectOptions::private('text/plain'));
    $head = $adapter->head($key);
    $signed = $adapter->sign($key, 60);
    $adapter->delete($key);

    sr065_assert($stored->visibility === 'private', 'MinIO object is private');
    sr065_assert($head->size > 0, 'MinIO object head returns size');
    sr065_assert(str_contains($signed->url, $bucket), 'MinIO signed URL references bucket');

    return [
        'bucket' => $bucket,
        'object_put' => true,
        'object_head' => true,
        'signed_url_generated' => true,
        'object_deleted' => true,
    ];
}

/**
 * @return array<string, mixed>
 */
function sr065_service_fixtures(): array
{
    $repository = new InMemoryEntitlementRepository();
    $now = '2026-07-01T00:00:00+00:00';

    $purchase = $repository->create(Entitlement::fromSnapshot(77, 2077, 'purchase', 'order_item', 5002, 6002, null, null, 1002, 'resource', ['type' => 'resources', 'resource_ids' => [1002]], null, 'sr065', $now, null, 100, 1, $now, $now));
    $vip = $repository->create(Entitlement::fromSnapshot(77, 2077, 'membership', 'order_item', 5003, 6003, 301, null, null, 'resources', ['type' => 'resources', 'resource_ids' => [1003, 1005], 'excluded_resource_ids' => [1004]], ['period_type' => 'month', 'period_key' => '2026-07', 'limit' => 2, 'remaining' => 1], 'sr065', $now, '2026-08-01T00:00:00+00:00', 90, 1, $now, $now));
    $refunded = $repository->create(Entitlement::fromSnapshot(77, 2077, 'purchase', 'order_item', 5006, 6006, null, null, 1006, 'resource', ['type' => 'resources', 'resource_ids' => [1006]], null, 'sr065', $now, null, 100, 1, $now, $now));
    $repository->save($refunded->revoke('2026-07-01T00:05:00+00:00', 1, 'refund:order:5006:item:6006'));

    $quotaStore = new Sr065QuotaStore();
    $quota = new QuotaService($quotaStore);
    $entitlements = [
        $purchase->id => $purchase,
        $vip->id => $vip,
    ];
    $access = new StockResource\Entitlements\Application\EntitlementService(
        $repository,
        static function (Entitlement $entitlement, AccessDecisionContext $context): array {
            if ($context->resourceId === 1005) {
                return ['available' => false, 'remaining' => 0, 'limit' => 2];
            }

            return $entitlement->quotaSnapshot ?? [];
        },
    );

    return [
        'now' => $now,
        'repository' => $repository,
        'entitlements' => $entitlements,
        'access' => $access,
        'quota' => $quota,
        'quota_store' => $quotaStore,
        'resources' => [
            1001 => ['resource_id' => 1001, 'status' => 'published', 'access_mode' => 'free', 'version_id' => 9001],
            1002 => ['resource_id' => 1002, 'status' => 'published', 'access_mode' => 'purchase', 'version_id' => 9002],
            1003 => ['resource_id' => 1003, 'status' => 'published', 'access_mode' => 'vip', 'version_id' => 9003],
            1004 => ['resource_id' => 1004, 'status' => 'published', 'access_mode' => 'purchase_or_vip', 'version_id' => 9004],
            1005 => ['resource_id' => 1005, 'status' => 'published', 'access_mode' => 'vip', 'version_id' => 9005],
            1006 => ['resource_id' => 1006, 'status' => 'published', 'access_mode' => 'purchase', 'version_id' => 9006],
            1007 => ['resource_id' => 1007, 'status' => 'draft', 'access_mode' => 'purchase', 'version_id' => 9007],
        ],
    ];
}

/**
 * @param array<string, mixed> $fixtures
 * @param list<string> $scenarioOrder
 * @return array<string, array<string, mixed>>
 */
function sr065_run_scenarios(array $fixtures, array $scenarioOrder): array
{
    $results = [];
    foreach ($scenarioOrder as $scenario) {
        $results[$scenario] = sr065_evaluate_scenario($fixtures, $scenario);
    }
    ksort($results);

    return $results;
}

/**
 * @param array<string, mixed> $fixtures
 * @return array<string, mixed>
 */
function sr065_evaluate_scenario(array $fixtures, string $scenario): array
{
    $resourceMap = [
        'free' => 1001,
        'single_purchase' => 1002,
        'vip' => 1003,
        'excluded' => 1004,
        'quota_exhausted' => 1005,
        'refund' => 1006,
        'unpublished' => 1007,
    ];
    $resourceId = $resourceMap[$scenario] ?? throw new RuntimeException('Unknown scenario: '.$scenario);
    $resource = $fixtures['resources'][$resourceId];
    $requestId = RequestIdFactory::fromIncomingHeader(sr065_request_id($scenario));

    $decision = $fixtures['access']->decide(new AccessDecisionContext(
        resourceId: $resourceId,
        userId: 77,
        accessMode: $resource['access_mode'],
        resourceStatus: $resource['status'],
        taxonomyTermIds: [],
        atUtc: $fixtures['now'],
    ));

    $dbRows = [
        ['table' => 'wp_sr_access_decision_fixture', 'request_id' => $requestId, 'resource_id' => $resourceId, 'decision' => $decision->allowed ? 'allow' : 'deny', 'reason' => $decision->reasonCode],
    ];

    if (! $decision->allowed) {
        return sr065_scenario_result($scenario, $requestId, false, $decision->reasonCode, $dbRows, []);
    }

    $reservationId = 'free-resource';
    if ($decision->entitlementId !== null) {
        $reservation = $fixtures['quota']->reserve($decision->entitlementId, 77, 'month', '2026-07', 2, $requestId, $fixtures['now']);
        sr065_assert($reservation->ok, 'quota reservation succeeds for '.$scenario);
        $reservationId = (string) $reservation->reservationId;
        $dbRows[] = ['table' => 'wp_sr_entitlement_counters', 'request_id' => $requestId, 'counter' => $reservation->counter];
    }

    $tokenService = new DownloadTokenService(
        new InMemoryDownloadTokenRepository(),
        'sr065-local-app-key',
        new FixedTokenBytes(hash('sha256', 'sr065-'.$scenario, true)),
    );
    $issue = $tokenService->issue(new DownloadTokenIssueRequest(
        requestId: $requestId,
        userId: 77,
        resourceId: $resourceId,
        versionId: $resource['version_id'],
        entitlementId: $decision->entitlementId,
        quotaReservationId: $reservationId,
        nowUtc: $fixtures['now'],
        ttlSeconds: 120,
    ));
    $consume = $tokenService->consume($issue->rawToken, 77, $resourceId, $resource['version_id'], '2026-07-01T00:00:30+00:00');
    sr065_assert($consume->ok, 'download token consume succeeds for '.$scenario);

    if ($decision->entitlementId !== null) {
        $commit = $fixtures['quota']->commit($reservationId, $requestId, '2026-07-01T00:00:31+00:00');
        sr065_assert($commit->ok, 'quota commit succeeds for '.$scenario);
        $dbRows[] = ['table' => 'wp_sr_entitlement_counters', 'request_id' => $requestId, 'counter' => $commit->counter];
    }
    $dbRows[] = ['table' => 'wp_sr_download_tokens', 'request_id' => $requestId, 'token_id' => $issue->tokenId, 'status' => $consume->status];
    $dbRows[] = ['table' => 'wp_sr_download_events', 'request_id' => $requestId, 'token_id' => $issue->tokenId, 'resource_id' => $resourceId, 'result' => 'redirected'];

    return sr065_scenario_result($scenario, $requestId, true, 'download_redirected', $dbRows, [
        'raw_token' => $issue->rawToken,
        'storage_key' => 'sr065/'.$resourceId.'/fixture.zip',
        'download_token_signed_url' => 'http://127.0.0.1:9002/indicator-assets/sr065/'.$resourceId.'/fixture.zip?X-Amz-Signature=secret',
    ]);
}

/**
 * @param list<array<string, mixed>> $dbRows
 * @param array<string, mixed> $sensitiveLogContext
 * @return array<string, mixed>
 */
function sr065_scenario_result(string $scenario, string $requestId, bool $allowed, string $reason, array $dbRows, array $sensitiveLogContext): array
{
    $sink = new InMemoryLogSink();
    $logger = new StructuredLogger($sink, new SensitiveFieldRedactor());
    $logger->info('sr065.scenario', 'SR-065 scenario evaluated', array_replace([
        'scenario' => $scenario,
        'request_id' => $requestId,
        'allowed' => $allowed,
        'reason' => $reason,
    ], $sensitiveLogContext));

    return [
        'scenario' => $scenario,
        'request_id' => $requestId,
        'allowed' => $allowed,
        'reason' => $reason,
        'db_rows' => $dbRows,
        'logs' => $sink->records(),
    ];
}

/**
 * @param array<string, mixed> $fixtures
 * @param array<string, array<string, mixed>> $results
 * @return array<string, mixed>
 */
function sr065_observability(array $results): array
{
    $requestIds = [];
    $logs = [];
    $databaseRows = [];
    foreach ($results as $result) {
        $requestIds[] = $result['request_id'];
        $logs = array_merge($logs, $result['logs']);
        $databaseRows = array_merge($databaseRows, $result['db_rows']);
    }

    $encodedLogs = json_encode($logs, JSON_THROW_ON_ERROR);
    $sanitized = ! str_contains($encodedLogs, 'private/')
        && ! str_contains($encodedLogs, 'X-Amz-Signature=secret')
        && ! str_contains($encodedLogs, 'eHh4')
        && str_contains($encodedLogs, '[REDACTED]');

    return [
        'request_ids' => $requestIds,
        'logs' => $logs,
        'database_rows' => $databaseRows,
        'sanitized_logs' => $sanitized,
    ];
}

/**
 * @return array<string, string>
 */
function sr065_env(string $root): array
{
    $path = is_readable($root.'/.env') ? $root.'/.env' : $root.'/.env.example';
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    sr065_assert(is_array($lines), 'env file is readable');

    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim(trim($value), "'\"");
    }

    return $env;
}

function sr065_mysqli(array $env, ?string $database = null): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $host = '127.0.0.1';
    $port = (int) ($env['DB_PORT'] ?? 3307);
    $user = 'root';
    $password = $env['DB_ROOT_PASSWORD'] ?? 'root-local-only';
    $mysqli = new mysqli($host, $user, $password, $database ?? '', $port);
    $mysqli->set_charset('utf8mb4');

    return $mysqli;
}

function sr065_mysqli_exec(mysqli $mysqli, string $sql): void
{
    $ok = $mysqli->query($sql);
    sr065_assert($ok === true, 'SQL statement succeeds');
}

function sr065_mysqli_multi_exec(mysqli $mysqli, string $sql): void
{
    $ok = $mysqli->multi_query($sql);
    sr065_assert($ok, 'schema multi query starts');
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());

    sr065_assert($mysqli->errno === 0, 'schema multi query completed: '.$mysqli->error);
}

function sr065_scalar_int(mysqli $mysqli, string $sql): int
{
    $result = $mysqli->query($sql);
    sr065_assert($result instanceof mysqli_result, 'scalar query succeeds');
    $row = $result->fetch_row();
    sr065_assert(is_array($row), 'scalar query returns row');

    return (int) $row[0];
}

/**
 * @param list<string> $command
 */
function sr065_command_succeeds(array $command, string $cwd, string $message): void
{
    $result = sr065_command_output($command, $cwd, null, $message);
    sr065_assert($result['exit_code'] === 0, $message.' failed: '.trim($result['stdout'].' '.$result['stderr']));
}

/**
 * @param list<string> $command
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function sr065_command_output(array $command, string $cwd, ?string $stdin, string $message): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, $cwd);
    sr065_assert(is_resource($process), $message.' could not start');

    fwrite($pipes[0], $stdin ?? '');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'exit_code' => proc_close($process),
        'stdout' => (string) $stdout,
        'stderr' => (string) $stderr,
    ];
}

function sr065_request_id(string $scenario): string
{
    return match ($scenario) {
        'free' => '00000000-0000-4000-8000-000000000001',
        'single_purchase' => '00000000-0000-4000-8000-000000000002',
        'vip' => '00000000-0000-4000-8000-000000000003',
        'excluded' => '00000000-0000-4000-8000-000000000004',
        'quota_exhausted' => '00000000-0000-4000-8000-000000000005',
        'refund' => '00000000-0000-4000-8000-000000000006',
        'unpublished' => '00000000-0000-4000-8000-000000000007',
        default => RequestIdFactory::generate(),
    };
}

sr065_require_runtime_classes(sr065_root());

final class Sr065QuotaStore implements QuotaCounterStore
{
    /** @var array<string, QuotaCounterRecord> */
    private array $counters = [];

    /** @var array<string, QuotaReservationRecord> */
    private array $reservations = [];

    public function withCounterForUpdate(
        int $entitlementId,
        int $userId,
        string $periodType,
        string $periodKey,
        int $limit,
        string $nowUtc,
        callable $callback,
    ): QuotaOperationResult {
        $key = $this->counterKey($entitlementId, $periodType, $periodKey);
        $this->counters[$key] ??= new QuotaCounterRecord(
            entitlementId: $entitlementId,
            userId: $userId,
            periodType: $periodType,
            periodKey: $periodKey,
            limitSnapshot: $limit,
            usedCount: 0,
            reservedCount: 0,
            lockVersion: 0,
            createdAt: $nowUtc,
            updatedAt: $nowUtc,
        );

        return $callback($this->counters[$key]);
    }

    public function findReservation(string $reservationId): ?QuotaReservationRecord
    {
        return $this->reservations[$reservationId] ?? null;
    }

    public function findReservationByRequestId(string $requestId): ?QuotaReservationRecord
    {
        foreach ($this->reservations as $reservation) {
            if ($reservation->requestId === $requestId) {
                return $reservation;
            }
        }

        return null;
    }

    public function saveReservation(QuotaReservationRecord $reservation): void
    {
        $this->reservations[$reservation->reservationId] = $reservation;
    }

    private function counterKey(int $entitlementId, string $periodType, string $periodKey): string
    {
        return $entitlementId.'|'.$periodType.'|'.$periodKey;
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo json_encode(sr065_run_full_chain_fixture(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
}
