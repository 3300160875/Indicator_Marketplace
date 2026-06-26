<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$fixtureFile = $root . '/tests/fixtures/resources/catalog.json';
$seedScript = $root . '/bin/seed-resources';

function sr020_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr020_decode_file(string $path): array
{
    $json = file_get_contents($path);
    sr020_assert(is_string($json), 'fixture file is readable: ' . $path);
    $decoded = json_decode($json, true);
    sr020_assert(is_array($decoded), 'fixture file contains valid JSON: ' . $path);

    return $decoded;
}

sr020_assert(is_file($fixtureFile), 'resource fixture catalog must exist');
sr020_assert(is_file($seedScript), 'seed script must exist');
sr020_assert(is_executable($seedScript), 'seed script must be executable');

$catalog = sr020_decode_file($fixtureFile);
sr020_assert(($catalog['schema_version'] ?? null) === 1, 'fixture catalog schema version is stable');
sr020_assert(is_array($catalog['resources'] ?? null), 'fixture catalog contains resources');
sr020_assert(count($catalog['resources']) === 20, 'fixture catalog contains exactly 20 resources');

$naturalKeys = [];
$slugs = [];
$accessModes = [];
$postStatuses = [];
$resourceStates = [];
$versionStates = [];
$scanStates = [];
$withNoVersion = 0;
$vipExcluded = 0;

foreach ($catalog['resources'] as $resource) {
    sr020_assert(is_array($resource), 'each fixture resource is an object');
    foreach (['natural_key', 'slug', 'title', 'post_status', 'meta', 'taxonomies'] as $required) {
        sr020_assert(array_key_exists($required, $resource), 'resource contains ' . $required);
    }

    $naturalKey = (string) $resource['natural_key'];
    $slug = (string) $resource['slug'];
    sr020_assert(preg_match('/^fixture-[a-z0-9-]+$/', $naturalKey) === 1, 'natural key is stable and synthetic: ' . $naturalKey);
    sr020_assert(! isset($naturalKeys[$naturalKey]), 'natural key is unique: ' . $naturalKey);
    sr020_assert(! isset($slugs[$slug]), 'slug is unique: ' . $slug);
    $naturalKeys[$naturalKey] = true;
    $slugs[$slug] = true;

    $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
    $versions = is_array($resource['versions'] ?? null) ? $resource['versions'] : [];
    $accessModes[(string) ($meta['_sr_access_mode'] ?? '')] = true;
    $postStatuses[(string) $resource['post_status']] = true;
    $resourceStates[(string) ($resource['fixture_state'] ?? '')] = true;

    if (($resource['fixture_state'] ?? null) === 'vip_excluded') {
        $vipExcluded++;
    }
    if ($versions === []) {
        $withNoVersion++;
    }

    foreach ($versions as $version) {
        sr020_assert(is_array($version), 'version fixture is an object');
        $versionStates[(string) ($version['status'] ?? '')] = true;
        $scanStates[(string) ($version['scan_status'] ?? '')] = true;
        sr020_assert(! str_contains(json_encode($version, JSON_THROW_ON_ERROR), 'real-paid'), 'fixtures do not contain real paid resource markers');
    }

    $serialized = json_encode($resource, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    foreach (['production', '真实付费', 'customer', 'token', 'secret'] as $forbidden) {
        sr020_assert(! str_contains(strtolower($serialized), strtolower($forbidden)), 'fixtures do not contain production/private marker: ' . $forbidden);
    }
}

foreach (['free', 'purchase', 'vip', 'purchase_or_vip', 'unavailable'] as $mode) {
    sr020_assert(isset($accessModes[$mode]), 'fixture access modes include ' . $mode);
}
foreach (['publish', 'draft'] as $status) {
    sr020_assert(isset($postStatuses[$status]), 'fixture post statuses include ' . $status);
}
foreach (['published', 'downlisted', 'no_version', 'vip_excluded'] as $state) {
    sr020_assert(isset($resourceStates[$state]), 'fixture resource states include ' . $state);
}
foreach (['active', 'draft', 'review', 'suspended', 'archived'] as $state) {
    sr020_assert(isset($versionStates[$state]), 'fixture version states include ' . $state);
}
foreach (['clean', 'pending', 'failed'] as $state) {
    sr020_assert(isset($scanStates[$state]), 'fixture scan states include ' . $state);
}
sr020_assert($withNoVersion >= 1, 'fixture catalog includes resources without versions');
sr020_assert($vipExcluded >= 1, 'fixture catalog includes VIP excluded resources');

$stateFile = tempnam(sys_get_temp_dir(), 'sr020-seed-');
sr020_assert(is_string($stateFile), 'temporary state file is created');
unlink($stateFile);
$cmd = escapeshellarg($seedScript) . ' --fixtures ' . escapeshellarg(dirname($fixtureFile)) . ' --state ' . escapeshellarg($stateFile);
exec($cmd, $firstOutput, $firstExit);
exec($cmd, $secondOutput, $secondExit);

sr020_assert($firstExit === 0, 'first seed run exits successfully');
sr020_assert($secondExit === 0, 'second seed run exits successfully');
$first = json_decode(implode("\n", $firstOutput), true);
$second = json_decode(implode("\n", $secondOutput), true);
sr020_assert(is_array($first), 'first seed run returns JSON summary');
sr020_assert(is_array($second), 'second seed run returns JSON summary');
sr020_assert($first['resources_created'] === 20, 'first seed run creates 20 resources');
sr020_assert($second['resources_created'] === 0, 'second seed run creates no duplicate resources');
sr020_assert($second['resources_updated'] === 20, 'second seed run updates existing resources idempotently');

$state = sr020_decode_file($stateFile);
unlink($stateFile);
sr020_assert(count($state['resources'] ?? []) === 20, 'seed state contains 20 unique resources after repeated runs');

echo "SR-020 fixture check passed.\n";
