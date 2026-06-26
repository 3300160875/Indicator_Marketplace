<?php

declare(strict_types=1);

namespace StockResource\Core\Seo;

final readonly class ResourceSeoDocument
{
    /**
     * @param  array<string, mixed>  $structuredData
     */
    public function __construct(
        public int $httpStatus,
        public string $canonicalUrl,
        public string $title,
        public string $description,
        public string $robots,
        public array $structuredData,
    ) {}
}
