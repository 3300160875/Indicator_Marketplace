<?php

declare(strict_types=1);

namespace StockResource\Core\Commerce\OrderSnapshot;

use StockResource\Core\Integration\Edd\EddOrderAdapter;

final readonly class OrderSnapshotService
{
    /**
     * @param  array<int, OrderItemBusinessSnapshot>  $existingByItemId
     * @return list<OrderItemBusinessSnapshot>
     */
    public function snapshotsForOrder(
        EddOrderAdapter $adapter,
        int $orderId,
        int $userId,
        array $existingByItemId = [],
    ): array {
        $order = $adapter->getOrder($orderId);
        if ($order->type === 'refund') {
            throw OrderSnapshotException::refundOrderNotAccessible($orderId);
        }

        if ($order->customer->userId < 1 || $userId < 1) {
            throw OrderSnapshotException::missingUserMapping($orderId);
        }

        if ($order->customer->userId !== $userId) {
            throw OrderSnapshotException::orderNotOwned($orderId, $userId);
        }

        $snapshots = [];
        foreach ($adapter->getItems($orderId) as $item) {
            if (isset($existingByItemId[$item->id])) {
                $snapshots[] = $existingByItemId[$item->id];

                continue;
            }

            $snapshots[] = OrderItemBusinessSnapshot::fromEdd($order, $item);
        }

        return $snapshots;
    }
}
