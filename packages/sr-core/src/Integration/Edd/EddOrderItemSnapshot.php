<?php

declare(strict_types=1);

namespace StockResource\Core\Integration\Edd;

use StockResource\Contracts\Value\Money;

final readonly class EddOrderItemSnapshot
{
    /**
     * @param  array<string, mixed>  $businessSnapshot
     */
    public function __construct(
        public int $id,
        public int $orderId,
        public int $downloadId,
        public int $priceId,
        public int $quantity,
        public Money $subtotal,
        public Money $tax,
        public Money $total,
        public array $businessSnapshot,
    ) {
        if ($this->id < 1 || $this->orderId < 1 || $this->downloadId < 1) {
            throw EddAdapterException::invalidShape('order_item', 'ids must be positive');
        }
    }
}
