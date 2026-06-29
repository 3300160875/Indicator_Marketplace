<?php

declare(strict_types=1);

namespace StockResource\Core\Commerce;

use StockResource\Contracts\Value\Money;

final readonly class PriceBook
{
    /**
     * @param  array<int, array<int, string>>  $prices
     */
    public function __construct(private array $prices) {}

    public function quote(int $downloadId, int $priceId): PriceQuote
    {
        $amount = $this->prices[$downloadId][$priceId] ?? null;
        if ($amount === null) {
            throw CommerceException::priceRequired($downloadId, $priceId);
        }

        return new PriceQuote(
            downloadId: $downloadId,
            priceId: $priceId,
            unitAmount: Money::fromString($amount),
        );
    }
}
