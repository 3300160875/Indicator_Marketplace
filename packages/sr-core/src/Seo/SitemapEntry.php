<?php

declare(strict_types=1);

namespace StockResource\Core\Seo;

final readonly class SitemapEntry
{
    public function __construct(
        public string $location,
        public string $lastModified,
        public string $changeFrequency,
        public float $priority,
    ) {}
}
