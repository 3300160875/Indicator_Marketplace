<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$runner = $root.'/tests/integration/sr065_full_chain_fixture.php';

require_once $runner;

if (! function_exists('sr065_run_full_chain_fixture')) {
    throw new RuntimeException('SR-065 integration runner did not expose sr065_run_full_chain_fixture.');
}

$result = sr065_run_full_chain_fixture();
$tracePath = __DIR__.'/full-chain-trace.json';
file_put_contents($tracePath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

if (($result['status'] ?? null) !== 'ok') {
    throw new RuntimeException('SR-065 integration fixture failed: '.json_encode($result, JSON_THROW_ON_ERROR));
}

$requiredScenarios = ['free', 'single_purchase', 'vip', 'excluded', 'quota_exhausted', 'refund', 'unpublished'];
$scenarioKeys = array_keys($result['scenarios'] ?? []);
foreach ($requiredScenarios as $scenario) {
    if (! in_array($scenario, $scenarioKeys, true)) {
        throw new RuntimeException('Missing scenario: '.$scenario);
    }
}

if (($result['install']['empty_database_install'] ?? null) !== 'ok') {
    throw new RuntimeException('Empty database install fixture did not complete.');
}
if (($result['topology']['edd_active'] ?? false) !== true) {
    throw new RuntimeException('EDD is not active in the live WordPress database.');
}
if (($result['minio']['object_put'] ?? false) !== true || ($result['minio']['object_head'] ?? false) !== true) {
    throw new RuntimeException('MinIO object chain did not complete.');
}
if (($result['order_independence']['forward'] ?? null) !== ($result['order_independence']['reverse'] ?? null)) {
    throw new RuntimeException('Integration fixture depends on execution order.');
}
if (($result['observability']['request_ids'] ?? []) === []) {
    throw new RuntimeException('Fixture did not record request IDs.');
}
if (($result['observability']['sanitized_logs'] ?? false) !== true) {
    throw new RuntimeException('Fixture logs are not sanitized.');
}

echo "SR-065 full-chain fixture checks passed\n";
