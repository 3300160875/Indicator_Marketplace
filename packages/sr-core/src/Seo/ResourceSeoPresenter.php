<?php

declare(strict_types=1);

namespace StockResource\Core\Seo;

use StockResource\Core\Dto\ResourceView;

final readonly class ResourceSeoPresenter
{
    public function __construct(
        private string $siteBaseUrl,
        private string $resourceBasePath = '/resources',
    ) {}

    public function publicResource(ResourceView $resource, ?string $title = null, ?string $description = null): ResourceSeoDocument
    {
        $canonicalUrl = $this->canonicalUrl($resource->slug);
        $resolvedTitle = self::cleanText($title ?? $resource->title);
        $resolvedDescription = self::description($description ?? $resource->excerpt ?: $resource->content);

        return new ResourceSeoDocument(
            httpStatus: 200,
            canonicalUrl: $canonicalUrl,
            title: $resolvedTitle,
            description: $resolvedDescription,
            robots: 'index,follow',
            structuredData: $this->structuredData($resource, $canonicalUrl, $resolvedDescription),
        );
    }

    public function downlistedResource(string $slug, ?string $title = null, ?string $description = null): ResourceSeoDocument
    {
        return new ResourceSeoDocument(
            httpStatus: 200,
            canonicalUrl: $this->canonicalUrl($slug),
            title: self::cleanText($title ?? '资源暂不可用'),
            description: self::description($description ?? '该资源当前暂不可用。'),
            robots: 'noindex,nofollow',
            structuredData: [],
        );
    }

    public function goneResource(string $slug, ?string $title = null, ?string $description = null): ResourceSeoDocument
    {
        return new ResourceSeoDocument(
            httpStatus: 410,
            canonicalUrl: $this->canonicalUrl($slug),
            title: self::cleanText($title ?? '资源已移除'),
            description: self::description($description ?? '该资源已永久移除。'),
            robots: 'noindex,nofollow',
            structuredData: [],
        );
    }

    private function canonicalUrl(string $slug): string
    {
        $baseUrl = rtrim(trim($this->siteBaseUrl), '/');
        $basePath = '/'.trim($this->resourceBasePath, '/');
        $cleanSlug = trim($slug, "/ \t\n\r\0\x0B");

        return $baseUrl.$basePath.'/'.rawurlencode($cleanSlug);
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredData(ResourceView $resource, string $canonicalUrl, string $description): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'name' => self::cleanText($resource->title),
            'description' => $description,
            'url' => $canonicalUrl,
            'version' => self::cleanText($resource->currentVersion->versionLabel),
            'inLanguage' => 'zh-CN',
        ];

        if ($resource->currentVersion->activatedAt !== null && trim($resource->currentVersion->activatedAt) !== '') {
            $data['dateModified'] = self::dateOnly($resource->currentVersion->activatedAt);
        }

        $fileFormat = $resource->meta['file_format'] ?? null;
        if (is_string($fileFormat) && trim($fileFormat) !== '') {
            $data['encodingFormat'] = trim($fileFormat);
        }

        $softwareVersions = $resource->meta['software_versions'] ?? null;
        if (is_array($softwareVersions)) {
            $requirements = array_values(array_filter(
                array_map(static fn (mixed $version): string => self::cleanText((string) $version), $softwareVersions),
                static fn (string $version): bool => $version !== '',
            ));
            if ($requirements !== []) {
                $data['softwareRequirements'] = implode(', ', $requirements);
            }
        }

        return $data;
    }

    private static function cleanText(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));
    }

    private static function description(string $value): string
    {
        $clean = self::cleanText($value);
        if (strlen($clean) <= 160) {
            return $clean;
        }

        return rtrim(substr($clean, 0, 157)).'...';
    }

    private static function dateOnly(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return substr(trim($value), 0, 10);
        }

        return gmdate('Y-m-d', $timestamp);
    }
}
