<?php

declare(strict_types=1);

namespace StockResource\Core\Commerce;

use StockResource\Contracts\Value\Money;

final readonly class ResourcePurchaseValidation
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
        public Money $unitAmount,
        public Money $subtotal,
        public Money $discountAmount,
        public Money $total,
        public bool $purchasable,
        public string $priceSource,
        public array $resourceMeta,
    ) {}
}
