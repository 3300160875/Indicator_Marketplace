<?php
declare(strict_types=1);

use StockResource\Core\Application\ResourceService;
use StockResource\Core\Dto\ResourceView;
use StockResource\Core\Dto\VersionView;
use StockResource\Core\Version\ResourceVersion;

$root = dirname(__DIR__, 3);
$files = [
    '/packages/sr-core/src/Version/ResourceVersionStatus.php',
    '/packages/sr-core/src/Version/ResourceVersionScanStatus.php',
    '/packages/sr-core/src/Version/ResourceVersion.php',
    '/packages/sr-core/src/Dto/VersionView.php',
    '/packages/sr-core/src/Dto/ResourceView.php',
    '/packages/sr-core/src/Application/ResourceService.php',
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

$service = new ResourceService();
$currentVersion = ResourceVersion::fromArray([
    'id' => 88,
    'resource_id' => 1001,
    'version_label' => '1.2.0',
    'status' => 'active',
    'is_current' => true,
    'storage_provider' => 'local_private',
    'storage_bucket' => 'private-bucket',
    'storage_key' => 'resource/1001/private.zip',
    'original_filename' => 'trend-private.zip',
    'mime_type' => 'application/zip',
    'file_size' => 4096,
    'sha256' => str_repeat('c', 64),
    'compatibility' => ['platform' => 'tongdaxin', 'software_versions' => ['通达信 7.60']],
    'scan_status' => 'clean',
    'scan_result' => ['engine' => 'fixture', 'internal_notes' => 'do not expose'],
    'release_notes' => '公开版本说明。',
    'created_by' => 501,
    'approved_by' => 601,
    'activated_at' => '2026-06-25 10:00:00',
    'created_at' => '2026-06-25 09:00:00',
    'updated_at' => '2026-06-25 10:00:00',
]);

$published = [
    'id' => 1001,
    'post_status' => 'publish',
    'slug' => 'tdx-trend',
    'title' => '通达信趋势指标',
    'excerpt' => '用于趋势识别的指标资源。',
    'content' => '<p>公开说明。</p>',
    'taxonomies' => [
        'download_category' => ['vip-formula'],
        'sr_platform' => ['tongdaxin'],
        'sr_indicator_type' => ['sub-chart'],
        'sr_content_type' => ['indicator'],
        'sr_internal_review' => ['hidden'],
    ],
    'meta' => [
        '_sr_access_mode' => 'purchase',
        '_sr_software_versions' => ['通达信 7.60'],
        '_sr_device' => 'desktop',
        '_sr_os' => 'windows',
        '_sr_file_format' => 'tn6',
        '_sr_charset' => 'gbk',
        '_sr_source_included' => 'yes',
        '_sr_future_function_status' => 'none',
        '_sr_l2_required' => 'no',
        '_sr_usage_scenarios' => '<p>趋势观察。</p>',
        '_sr_limitations' => '<p>仅辅助判断。</p>',
        '_sr_disclaimer_version' => 'risk-v1',
        '_sr_rights_record_id' => 32,
        '_sr_risk_level' => 'medium',
        '_sr_internal_notes' => 'do not expose',
        'storage_key' => 'resource/1001/leak.zip',
    ],
];

$view = $service->publicView($published, $currentVersion);
sr_assert($view instanceof ResourceView, 'published resource returns a ResourceView DTO');
sr_assert($view->currentVersion instanceof VersionView, 'published resource returns a VersionView DTO');

$payload = $view->toArray();
sr_same(1001, $payload['id'], 'resource DTO exposes id');
sr_same('purchase', $payload['access_mode'], 'resource DTO exposes access mode');
sr_same(88, $payload['current_version']['id'], 'resource DTO exposes current version id');
sr_same('1.2.0', $payload['current_version']['version_label'], 'resource DTO exposes current version label');
sr_same('clean', $payload['current_version']['scan_status'], 'resource DTO exposes scan status');
sr_same(['tongdaxin'], $payload['taxonomies']['sr_platform'], 'resource DTO exposes public taxonomies');
sr_assert(! isset($payload['taxonomies']['sr_internal_review']), 'resource DTO excludes unknown taxonomies');
sr_same(['通达信 7.60'], $payload['meta']['software_versions'], 'resource DTO maps public meta keys');

$encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
sr_assert(! str_contains($encoded, '"_sr_'), 'public DTO does not expose raw post meta keys');
foreach (['storage_key', 'storage_bucket', 'storage_provider', 'sha256', 'internal_notes', '_sr_rights_record_id', '_sr_risk_level', '_sr_internal_notes'] as $forbidden) {
    sr_assert(! str_contains($encoded, $forbidden), 'public DTO does not expose internal field: ' . $forbidden);
}

$draft = $published;
$draft['id'] = 1002;
$draft['post_status'] = 'draft';
sr_same(null, $service->publicView($draft, $currentVersion), 'draft resource cannot be fetched through public service');

$unavailable = $published;
$unavailable['id'] = 1003;
$unavailable['meta']['_sr_access_mode'] = 'unavailable';
sr_same(null, $service->publicView($unavailable, $currentVersion), 'unavailable resource cannot be fetched through public service');

$blocked = $published;
$blocked['id'] = 1004;
$blocked['meta']['_sr_risk_level'] = 'blocked';
sr_same(null, $service->publicView($blocked, $currentVersion), 'blocked resource cannot be fetched through public service');

$noVersion = $published;
$noVersion['id'] = 1005;
sr_same(null, $service->publicView($noVersion, null), 'resource without current version cannot be fetched through public service');

echo "SR-017 resource service check: ok\n";
