<?php

declare(strict_types=1);

namespace StockResource\Core\Integration\Edd;

use StockResource\Contracts\Value\Money;

final readonly class EddOrderSnapshot
{
    public function __construct(
        public int $id,
        public string $type,
        public string $status,
        public Money $subtotal,
        public Money $tax,
        public Money $total,
        public string $currency,
        public EddCustomerSnapshot $customer,
        public string $createdAt,
        public string $completedAt,
    ) {
        if ($this->id < 1) {
            throw EddAdapterException::invalidShape('order', 'id must be positive');
        }
    }
}
