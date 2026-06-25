<?php
declare(strict_types=1);

namespace StockResource\Contracts\Dto;

use StockResource\Contracts\Exception\ValidationException;
use StockResource\Contracts\Value\PositiveId;
use StockResource\Contracts\Value\UtcDateTime;

final readonly class OrderRefundedEvent
{
    /** @param list<PositiveId> $refundedOrderItemIds */
    public function __construct(
        public PositiveId $orderId,
        public UtcDateTime $refundedAt,
        public array $refundedOrderItemIds,
        public bool $fullRefund,
    ) {
        if ([] === $refundedOrderItemIds) {
            throw new ValidationException('Refunded order event requires at least one order item id.');
        }
        foreach ($refundedOrderItemIds as $refundedOrderItemId) {
            if (! $refundedOrderItemId instanceof PositiveId) {
                throw new ValidationException('Refunded order item ids must be PositiveId instances.');
            }
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId->toInt(),
            'refunded_at' => $this->refundedAt->toString(),
            'refunded_order_item_ids' => array_map(static fn (PositiveId $id): int => $id->toInt(), $this->refundedOrderItemIds),
            'full_refund' => $this->fullRefund,
        ];
    }
}
