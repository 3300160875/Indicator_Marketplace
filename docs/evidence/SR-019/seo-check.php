<?php

declare(strict_types=1);

use StockResource\Core\Dto\ResourceView;
use StockResource\Core\Dto\VersionView;
use StockResource\Core\Seo\ResourceSeoPresenter;
use StockResource\Core\Seo\SitemapEntryFactory;
use StockResource\Core\Seo\SitemapXmlRenderer;

$root = dirname(__DIR__, 3);
$core = $root.'/packages/sr-core';

foreach ([
    '/src/Dto/VersionView.php',
    '/src/Dto/ResourceView.php',
    '/src/Seo/ResourceSeoDocument.php',
    '/src/Seo/ResourceSeoPresenter.php',
    '/src/Seo/SitemapEntry.php',
    '/src/Seo/SitemapEntryFactory.php',
    '/src/Seo/SitemapXmlRenderer.php',
] as $sourceFile) {
    require_once $core.$sourceFile;
}

function sr019_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr019_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

$version = new VersionView(
    id: 9001,
    resourceId: 501,
    versionLabel: '2.3.1',
    status: 'active',
    scanStatus: 'clean',
    fileSize: 65536,
    compatibility: ['platform' => ['tongdaxin'], 'software_versions' => ['通达信 7.60']],
    releaseNotes: '公开版本说明。',
    activatedAt: '2026-06-25T10:00:00Z',
);

$resource = new ResourceView(
    id: 501,
    slug: 'tdx-trend',
    title: '通达信趋势指标',
    excerpt: '用于趋势观察的指标资源，仅作为学习和研究辅助。',
    content: '<p>公开说明，强调风险自担。</p>',
    accessMode: 'purchase',
    taxonomies: [
        'download_category' => ['vip-formula'],
        'sr_platform' => ['tongdaxin'],
        'sr_indicator_type' => ['sub-chart'],
        'sr_content_type' => ['indicator'],
    ],
    meta: [
        'software_versions' => ['通达信 7.60'],
        'file_format' => 'tn6',
        'limitations' => '<p>不构成投资建议。</p>',
    ],
    currentVersion: $version,
);

$presenter = new ResourceSeoPresenter('https://example.test', '/resources');
$document = $presenter->publicResource(
    $resource,
    title: '自定义 SEO 标题',
    description: '自定义 SEO 描述，说明资源用途但不承诺收益。',
);

sr019_same(200, $document->httpStatus, 'published resources are indexable documents');
sr019_same('https://example.test/resources/tdx-trend', $document->canonicalUrl, 'canonical URL is stable and slug based');
sr019_same('自定义 SEO 标题', $document->title, 'resource SEO title is controllable');
sr019_same('自定义 SEO 描述，说明资源用途但不承诺收益。', $document->description, 'resource SEO description is controllable');
sr019_same('index,follow', $document->robots, 'published public resource is indexable');
sr019_same('通达信趋势指标', $document->structuredData['name'], 'structured data uses public resource name');
sr019_same('https://example.test/resources/tdx-trend', $document->structuredData['url'], 'structured data points to canonical URL');
sr019_same('2.3.1', $document->structuredData['version'], 'structured data includes public current version');

$encodedStructuredData = json_encode($document->structuredData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
foreach (['收益保证', '保证收益', '预期收益', '稳赚', 'profit guarantee', 'guaranteed return', 'offers', 'aggregateRating'] as $forbidden) {
    sr019_assert(! str_contains($encodedStructuredData, $forbidden), 'structured data excludes earnings or commercial promise marker: '.$forbidden);
}

$downlisted = $presenter->downlistedResource('tdx-trend');
sr019_same(200, $downlisted->httpStatus, 'downlisted resources keep a recoverable page strategy');
sr019_same('noindex,nofollow', $downlisted->robots, 'downlisted resources are explicitly noindex');
sr019_same('https://example.test/resources/tdx-trend', $downlisted->canonicalUrl, 'downlisted canonical remains stable');
sr019_same([], $downlisted->structuredData, 'downlisted resources do not emit structured data');

$gone = $presenter->goneResource('tdx-removed');
sr019_same(410, $gone->httpStatus, 'permanently removed resources use explicit 410 strategy');
sr019_same('noindex,nofollow', $gone->robots, '410 resources are explicitly noindex');
sr019_same([], $gone->structuredData, '410 resources do not emit structured data');

$entry = (new SitemapEntryFactory('https://example.test', '/resources'))->forResource($resource);
sr019_same('https://example.test/resources/tdx-trend', $entry->location, 'sitemap entry uses canonical URL');
sr019_same('2026-06-25', $entry->lastModified, 'sitemap entry normalizes last modified date');
sr019_same('monthly', $entry->changeFrequency, 'sitemap entry has conservative change frequency');
sr019_same(0.6, $entry->priority, 'sitemap entry has stable priority');

$xml = (new SitemapXmlRenderer)->render([$entry]);
sr019_assert(str_contains($xml, '<loc>https://example.test/resources/tdx-trend</loc>'), 'sitemap XML renders canonical loc');
sr019_assert(str_contains($xml, '<lastmod>2026-06-25</lastmod>'), 'sitemap XML renders lastmod');

echo "SR-019 SEO checks passed.\n";
