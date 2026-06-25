<?php
declare(strict_types=1);

use StockResource\Core\Content\Meta\DownloadMetaCatalog;
use StockResource\Core\Content\Meta\DownloadMetaDefinition;

$root = dirname(__DIR__, 3);
$files = [
    '/packages/sr-core/src/Content/Meta/DownloadMetaDefinition.php',
    '/packages/sr-core/src/Content/Meta/DownloadMetaCatalog.php',
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

$catalog = DownloadMetaCatalog::defaults();
$definitions = $catalog->definitions();

sr_assert($catalog->has('_sr_product_type'), 'product type meta is defined');
sr_assert($catalog->has('_sr_access_mode'), 'access mode meta is defined');
sr_assert($catalog->has('_sr_future_function_status'), 'future function status meta is defined');
sr_assert($catalog->has('_sr_rights_record_id'), 'rights record id meta is defined');
sr_assert($catalog->has('_sr_related_resource_ids'), 'related resource ids meta is defined');
sr_same(23, count($definitions), 'all SR-014 resource meta keys are cataloged');

$accessMode = $catalog->get('_sr_access_mode');
sr_same('enum', $accessMode->type(), 'access mode is an enum field');
sr_same('unavailable', $accessMode->default(), 'access mode default is unavailable until configured');
sr_same(['free', 'purchase', 'vip', 'purchase_or_vip', 'unavailable'], $accessMode->enumValues(), 'access mode enum values match OpenAPI');
sr_same('purchase_or_vip', $accessMode->sanitize('purchase_or_vip'), 'valid enum value is preserved');
sr_same('unavailable', $accessMode->sanitize('invalid'), 'invalid access mode falls back to safest mode');

$futureFunction = $catalog->get('_sr_future_function_status');
sr_same('unknown', $futureFunction->default(), 'future function default is unknown');
sr_same('unknown', $futureFunction->sanitize(null), 'unverified future function status stays unknown');
sr_same('unknown', $futureFunction->sanitize(false), 'unverified future function status is not coerced to false/none');
sr_same('none', $futureFunction->sanitize('none'), 'verified no-future-function status is preserved');

$sourceIncluded = $catalog->get('_sr_source_included');
sr_same('unknown', $sourceIncluded->sanitize(false), 'source included unknown is not coerced to no');

$softwareVersions = $catalog->get('_sr_software_versions');
sr_same(['通达信 7.60', '同花顺 9'], $softwareVersions->sanitize('["通达信 7.60","同花顺 9"]'), 'JSON array strings are decoded');
sr_same([], $softwareVersions->sanitize('{"not":"array"}'), 'JSON object is rejected for array field');
$softwareVersionsArgs = $softwareVersions->registrationArgs(static fn(string $capability): bool => true);
sr_same('string', $softwareVersionsArgs['show_in_rest']['schema']['items']['type'], 'array REST schema defines item type for WordPress');

$parameters = $catalog->get('_sr_parameters_json');
sr_same(['n' => 20, 'mode' => 'fast'], $parameters->sanitize('{"n":20,"mode":"fast"}'), 'JSON object strings are decoded');
sr_same([], $parameters->sanitize('["not-object"]'), 'JSON array is rejected for object field');
$parametersArgs = $parameters->registrationArgs(static fn(string $capability): bool => true);
sr_same(true, $parametersArgs['show_in_rest']['schema']['additionalProperties'], 'object REST schema allows structured parameter keys');

$installSteps = $catalog->get('_sr_install_steps');
sr_same('<p>Install</p>', $installSteps->sanitize('<p>Install</p><script>alert(1)</script>'), 'HTML fields strip script tags and script content');

$currentVersion = $catalog->get('_sr_current_version_id');
sr_same(42, $currentVersion->sanitize('42'), 'BIGINT fields normalize numeric strings');
sr_same(null, $currentVersion->sanitize('not-a-number'), 'invalid nullable BIGINT becomes null');
$currentVersionArgs = $currentVersion->registrationArgs(static fn(string $capability): bool => true);
sr_same('integer', $currentVersionArgs['type'], 'nullable BIGINT still registers as WordPress integer meta');
sr_same(['integer', 'null'], $currentVersionArgs['show_in_rest']['schema']['type'], 'nullable BIGINT is nullable only in REST schema');

$featured = $catalog->get('_sr_featured');
sr_same(false, $featured->default(), 'featured default is false');
sr_same(true, $featured->sanitize('1'), 'boolean fields normalize truthy values');

$publicKeys = array_map(
    static fn(DownloadMetaDefinition $definition): string => $definition->key(),
    $catalog->publicDefinitions(),
);
sort($publicKeys);
sr_same([
    '_sr_access_mode',
    '_sr_charset',
    '_sr_current_version_id',
    '_sr_device',
    '_sr_disclaimer_version',
    '_sr_faq_json',
    '_sr_file_format',
    '_sr_future_function_status',
    '_sr_install_steps',
    '_sr_l2_required',
    '_sr_limitations',
    '_sr_os',
    '_sr_parameters_json',
    '_sr_related_resource_ids',
    '_sr_software_versions',
    '_sr_source_included',
    '_sr_usage_scenarios',
], $publicKeys, 'REST catalog exposes only public resource fields');

foreach ($definitions as $definition) {
    $args = $definition->registrationArgs(static fn(string $capability): bool => $capability === 'edit_sr_resource_meta');
    sr_assert(is_callable($args['sanitize_callback']), $definition->key() . ' has a sanitize callback');
    sr_assert(is_callable($args['auth_callback']), $definition->key() . ' has an auth callback');
    sr_assert($args['single'] === true, $definition->key() . ' is registered as single meta');
}

$rightsRecord = $catalog->get('_sr_rights_record_id');
$rightsArgs = $rightsRecord->registrationArgs(static fn(string $capability): bool => false);
sr_same(false, $rightsArgs['show_in_rest'], 'rights record id is not public REST meta');
sr_same(false, $rightsArgs['auth_callback'](), 'auth callback denies editing without capability');

$riskLevel = $catalog->get('_sr_risk_level');
sr_same(false, $riskLevel->registrationArgs()['show_in_rest'], 'raw compliance risk level is not public REST meta');

$accessArgs = $accessMode->registrationArgs(static fn(string $capability): bool => true);
sr_same('string', $accessArgs['type'], 'enum fields register as WordPress string meta');
sr_same(true, is_array($accessArgs['show_in_rest']), 'public fields expose REST schema');
sr_same(['free', 'purchase', 'vip', 'purchase_or_vip', 'unavailable'], $accessArgs['show_in_rest']['schema']['enum'], 'REST schema includes enum values');

echo "SR-014 meta schema check: ok\n";
