<?php

declare(strict_types=1);

namespace StockResource\Core\Commerce;

use StockResource\Contracts\Value\Money;

final readonly class PriceQuote
{
    public function __construct(
        public int $downloadId,
        public int $priceId,
        public Money $unitAmount,
        public string $source = 'SERVER_RECALCULATED',
    ) {}
}
