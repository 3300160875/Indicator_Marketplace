<?php

declare(strict_types=1);

namespace StockResource\Core\Integration\Edd;

final readonly class EddCustomerSnapshot
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $email,
        public string $name,
    ) {
        if ($this->id < 1) {
            throw EddAdapterException::invalidShape('customer', 'id must be positive');
        }
    }
}
