<?php
declare(strict_types=1);

namespace StockResource\Contracts\Dto;

use StockResource\Contracts\Exception\ValidationException;
use StockResource\Contracts\Value\PositiveId;
use StockResource\Contracts\Value\UtcDateTime;

final readonly class OrderCompletedEvent
{
    /** @param list<PositiveId> $orderItemIds */
    public function __construct(
        public PositiveId $orderId,
        public PositiveId $customerId,
        public UtcDateTime $completedAt,
        public array $orderItemIds,
    ) {
        if ([] === $orderItemIds) {
            throw new ValidationException('Completed order event requires at least one order item id.');
        }
        foreach ($orderItemIds as $orderItemId) {
            if (! $orderItemId instanceof PositiveId) {
                throw new ValidationException('Completed order item ids must be PositiveId instances.');
            }
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId->toInt(),
            'customer_id' => $this->customerId->toInt(),
            'completed_at' => $this->completedAt->toString(),
            'order_item_ids' => array_map(static fn (PositiveId $id): int => $id->toInt(), $this->orderItemIds),
        ];
    }
}
