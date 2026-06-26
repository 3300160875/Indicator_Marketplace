<?php
declare(strict_types=1);

use StockResource\Core\Application\ResourceService;
use StockResource\Core\Content\Taxonomy\ControlledVocabulary;
use StockResource\Core\Content\Taxonomy\TaxonomyCatalog;
use StockResource\Core\Dto\ResourceView;
use StockResource\Core\Rest\Public\PublicResourceCollection;
use StockResource\Core\Rest\Public\PublicResourceQuery;
use StockResource\Core\Rest\Public\PublicRestError;
use StockResource\Core\Rest\Public\PublicRestRouteCatalog;
use StockResource\Core\Rest\Public\PublicTaxonomyVocabulary;
use StockResource\Core\Version\ResourceVersion;
use StockResource\Core\Version\ResourceVersionScanStatus;
use StockResource\Core\Version\ResourceVersionStatus;

$root = dirname(__DIR__, 3);
$core = $root . '/packages/sr-core';

foreach ([
    '/src/Dto/VersionView.php',
    '/src/Dto/ResourceView.php',
    '/src/Application/ResourceService.php',
    '/src/Content/Taxonomy/TaxonomyDefinition.php',
    '/src/Content/Taxonomy/TaxonomyCatalog.php',
    '/src/Content/Taxonomy/ControlledVocabulary.php',
    '/src/Version/ResourceVersionStatus.php',
    '/src/Version/ResourceVersionScanStatus.php',
    '/src/Version/ResourceVersion.php',
    '/src/Rest/Public/PublicRestError.php',
    '/src/Rest/Public/PublicRestRoute.php',
    '/src/Rest/Public/PublicRestRouteCatalog.php',
    '/src/Rest/Public/PublicResourceQuery.php',
    '/src/Rest/Public/PublicResourceCollection.php',
    '/src/Rest/Public/PublicTaxonomyVocabulary.php',
] as $sourceFile) {
    require_once $core . $sourceFile;
}

function sr018_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr018_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

$routes = PublicRestRouteCatalog::defaults()->routes();
sr018_same(['GET /resources', 'GET /resources/{idOrSlug}', 'GET /taxonomies'], array_map(
    static fn($route): string => $route->method() . ' ' . $route->path(),
    $routes,
), 'public route catalog exposes only the approved public GET routes');
foreach ($routes as $route) {
    sr018_same('sr/v1', $route->namespace(), 'route namespace is stable');
    sr018_same(true, $route->permissionCallback()(), 'public endpoints use an explicit public permission callback');
}

$query = PublicResourceQuery::fromArray([
    'search' => '  Alpha  Trend  ',
    'platform' => 'tongdaxin',
    'indicator_type' => 'sub-chart',
    'content_type' => 'indicator',
    'category' => 'vip-formula',
    'page' => '2',
    'per_page' => '24',
    'sort' => 'title_asc',
]);
sr018_same([
    'category' => 'vip-formula',
    'content_type' => 'indicator',
    'indicator_type' => 'sub-chart',
    'page' => 2,
    'per_page' => 24,
    'platform' => 'tongdaxin',
    'search' => 'Alpha Trend',
    'sort' => 'title_asc',
], $query->canonicalParams(), 'canonical public query params are stable and sorted');
sr018_same(
    'category=vip-formula&content_type=indicator&indicator_type=sub-chart&page=2&per_page=24&platform=tongdaxin&search=Alpha%20Trend&sort=title_asc',
    $query->canonicalQueryString(),
    'canonical query string uses RFC3986 encoding and deterministic key order',
);

try {
    PublicResourceQuery::fromArray(['unknown_filter' => 'bad']);
    throw new RuntimeException('unknown filters must fail');
} catch (PublicRestError $error) {
    sr018_same('sr_invalid_filter', $error->code(), 'unknown filters use a stable invalid-filter error code');
    sr018_same(400, $error->status(), 'unknown filters are bad requests');
}

try {
    PublicResourceQuery::fromArray(['platform' => '***']);
    throw new RuntimeException('invalid filter values must fail');
} catch (PublicRestError $error) {
    sr018_same('sr_invalid_filter', $error->code(), 'invalid filter values use stable invalid-filter error code');
}

$service = new ResourceService();
$currentVersion = ResourceVersion::fromArray([
    'id' => 101,
    'resource_id' => 10,
    'version_label' => '1.2.0',
    'status' => ResourceVersionStatus::Active->value,
    'scan_status' => ResourceVersionScanStatus::Clean->value,
    'is_current' => true,
    'storage_key' => 'private/object-key.zip',
    'sha256' => str_repeat('a', 64),
    'file_size' => 123456,
    'compatibility' => ['platform' => ['tongdaxin']],
    'release_notes' => 'Public release notes',
    'created_by' => 501,
    'activated_at' => '2026-06-25T00:00:00Z',
    'created_at' => '2026-06-25T00:00:00Z',
    'updated_at' => '2026-06-25T00:00:00Z',
]);

$published = $service->publicView([
    'id' => 10,
    'slug' => 'alpha-trend',
    'title' => 'Alpha Trend',
    'excerpt' => 'Trend indicator.',
    'content' => 'Visible public content.',
    'post_status' => 'publish',
    'meta' => ['_sr_access_mode' => 'vip'],
    'taxonomies' => [
        'download_category' => ['vip-formula'],
        'sr_platform' => ['tongdaxin'],
        'sr_indicator_type' => ['sub-chart'],
        'sr_content_type' => ['indicator'],
    ],
], $currentVersion);
$draft = $service->publicView([
    'id' => 11,
    'slug' => 'draft-only',
    'title' => 'Draft Only',
    'post_status' => 'draft',
    'meta' => ['_sr_access_mode' => 'vip'],
], $currentVersion);
sr018_assert($published instanceof ResourceView, 'published resource has a public DTO');
sr018_same(null, $draft, 'draft resource is not exposed to public REST responses');

$collection = PublicResourceCollection::fromViews([$published]);
$listResponse = $collection->list(PublicResourceQuery::fromArray([
    'search' => 'Alpha',
    'platform' => 'tongdaxin',
    'indicator_type' => 'sub-chart',
    'content_type' => 'indicator',
    'category' => 'vip-formula',
]));
sr018_same(1, $listResponse['pagination']['total'], 'public resource list excludes unavailable DTOs');
sr018_same('alpha-trend', $listResponse['data'][0]['slug'], 'public resource list returns matching resource data');
sr018_assert(! str_contains(json_encode($listResponse, JSON_THROW_ON_ERROR), 'private/object-key.zip'), 'public list never leaks storage keys');

$detailResponse = $collection->detail('alpha-trend');
sr018_same('alpha-trend', $detailResponse['data']['slug'], 'detail lookup supports slug');
try {
    $collection->detail('draft-only');
    throw new RuntimeException('unpublished detail must not resolve');
} catch (PublicRestError $error) {
    sr018_same('sr_resource_unavailable', $error->code(), 'missing or unpublished detail uses stable unavailable error code');
    sr018_same(404, $error->status(), 'missing or unpublished detail returns not found');
}

$taxonomies = PublicTaxonomyVocabulary::fromCatalog(
    TaxonomyCatalog::defaults(),
    ControlledVocabulary::defaults(),
)->toArray();
sr018_same('platform', $taxonomies['data']['sr_platform']['rest_key'], 'taxonomy vocabulary exposes stable rest key');
sr018_same('tongdaxin', $taxonomies['data']['sr_platform']['terms'][0]['slug'], 'taxonomy vocabulary exposes controlled terms');

$openapi = file_get_contents($root . '/docs/contracts/openapi.yaml');
sr018_assert(is_string($openapi), 'OpenAPI contract is readable');
foreach ([
    'canonical_query',
    'sr_invalid_filter',
    'ResourceView',
    'VersionView',
    'PublicTaxonomyVocabulary',
] as $needle) {
    sr018_assert(str_contains($openapi, $needle), 'OpenAPI contract includes ' . $needle);
}

echo "SR-018 public REST checks passed.\n";
