<?php

declare(strict_types=1);

namespace StockResource\Core\Commerce;

final readonly class ResourcePurchaseRequest
{
    /**
     * @param  array<string, mixed>  $resourceMeta
     */
    public function __construct(
        public int $downloadId,
        public ProductType $productType,
        public AccessMode $accessMode,
        public int $priceId,
        public int $quantity,
        public string $clientUnitAmount,
        public ?string $discountCode,
        public array $resourceMeta,
    ) {}
}
