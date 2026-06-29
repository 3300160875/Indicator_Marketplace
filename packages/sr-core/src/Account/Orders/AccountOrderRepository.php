<?php

declare(strict_types=1);

namespace StockResource\Core\Account\Orders;

final readonly class AccountOrderRepository
{
    /**
     * @param  list<array<string, mixed>>  $orders
     */
    public function __construct(private array $orders) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        return array_values(array_filter(
            $this->orders,
            static fn (array $order): bool => (int) ($order['user_id'] ?? 0) === $userId,
        ));
    }
}
