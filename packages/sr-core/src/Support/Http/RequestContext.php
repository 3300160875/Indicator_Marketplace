<?php
declare(strict_types=1);

namespace StockResource\Core\Support\Http;

final readonly class RequestContext
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $requestId,
        public array $attributes = [],
    ) {
    }
}
