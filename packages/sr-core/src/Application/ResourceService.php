<?php
declare(strict_types=1);

namespace StockResource\Core\Application;

use StockResource\Core\Dto\ResourceView;
use StockResource\Core\Dto\VersionView;
use StockResource\Core\Version\ResourceVersion;
use StockResource\Core\Version\ResourceVersionScanStatus;
use StockResource\Core\Version\ResourceVersionStatus;

final readonly class ResourceService
{
    /** @var array<string, string> */
    private const PUBLIC_META_MAP = [
        '_sr_software_versions' => 'software_versions',
        '_sr_device' => 'device',
        '_sr_os' => 'os',
        '_sr_file_format' => 'file_format',
        '_sr_charset' => 'charset',
        '_sr_source_included' => 'source_included',
        '_sr_future_function_status' => 'future_function_status',
        '_sr_l2_required' => 'l2_required',
        '_sr_usage_scenarios' => 'usage_scenarios',
        '_sr_limitations' => 'limitations',
        '_sr_disclaimer_version' => 'disclaimer_version',
    ];

    /** @var list<string> */
    private const PUBLIC_TAXONOMIES = [
        'download_category',
        'sr_platform',
        'sr_indicator_type',
        'sr_strategy_tag',
        'sr_content_type',
    ];

    /**
     * @param array<string, mixed> $resource
     */
    public function publicView(array $resource, ?ResourceVersion $currentVersion): ?ResourceView
    {
        if (! $this->isPublicResource($resource)) {
            return null;
        }

        if (! $this->isPublicCurrentVersion($currentVersion)) {
            return null;
        }

        $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];

        return new ResourceView(
            id: max(0, (int) ($resource['id'] ?? 0)),
            slug: trim((string) ($resource['slug'] ?? '')),
            title: trim((string) ($resource['title'] ?? '')),
            excerpt: trim((string) ($resource['excerpt'] ?? '')),
            content: trim((string) ($resource['content'] ?? '')),
            accessMode: (string) ($meta['_sr_access_mode'] ?? 'unavailable'),
            taxonomies: $this->publicTaxonomies($resource['taxonomies'] ?? []),
            meta: $this->publicMeta($meta),
            currentVersion: VersionView::fromResourceVersion($currentVersion),
        );
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function isPublicResource(array $resource): bool
    {
        $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];

        return max(0, (int) ($resource['id'] ?? 0)) > 0
            && ($resource['post_status'] ?? null) === 'publish'
            && ($meta['_sr_access_mode'] ?? 'unavailable') !== 'unavailable'
            && ($meta['_sr_risk_level'] ?? null) !== 'blocked';
    }

    private function isPublicCurrentVersion(?ResourceVersion $version): bool
    {
        return $version !== null
            && $version->isCurrent
            && $version->status === ResourceVersionStatus::Active
            && $version->scanStatus === ResourceVersionScanStatus::Clean;
    }

    /**
     * @param mixed $taxonomies
     * @return array<string, list<string>>
     */
    private function publicTaxonomies(mixed $taxonomies): array
    {
        if (! is_array($taxonomies)) {
            return [];
        }

        $public = [];
        foreach ($taxonomies as $taxonomy => $terms) {
            if (! is_string($taxonomy) || ! in_array($taxonomy, self::PUBLIC_TAXONOMIES, true) || ! is_array($terms)) {
                continue;
            }

            $public[$taxonomy] = array_values(array_filter(
                array_map(static fn(mixed $term): string => trim((string) $term), $terms),
                static fn(string $term): bool => $term !== '',
            ));
        }

        return $public;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function publicMeta(array $meta): array
    {
        $public = [];
        foreach (self::PUBLIC_META_MAP as $internalKey => $publicKey) {
            if (array_key_exists($internalKey, $meta)) {
                $public[$publicKey] = $meta[$internalKey];
            }
        }

        return $public;
    }
}
