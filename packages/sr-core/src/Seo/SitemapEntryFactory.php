<?php

declare(strict_types=1);

namespace StockResource\Core\Seo;

use StockResource\Core\Dto\ResourceView;

final readonly class SitemapEntryFactory
{
    public function __construct(
        private string $siteBaseUrl,
        private string $resourceBasePath = '/resources',
    ) {}

    public function forResource(ResourceView $resource): SitemapEntry
    {
        return new SitemapEntry(
            location: $this->canonicalUrl($resource->slug),
            lastModified: self::lastModified($resource->currentVersion->activatedAt),
            changeFrequency: 'monthly',
            priority: 0.6,
        );
    }

    private function canonicalUrl(string $slug): string
    {
        return rtrim(trim($this->siteBaseUrl), '/').'/'.trim($this->resourceBasePath, '/').'/'.rawurlencode(trim($slug, "/ \t\n\r\0\x0B"));
    }

    private static function lastModified(?string $value): string
    {
        $timestamp = $value === null ? false : strtotime($value);
        if ($timestamp === false) {
            return gmdate('Y-m-d');
        }

        return gmdate('Y-m-d', $timestamp);
    }
}
