<?php

declare(strict_types=1);

namespace StockResource\Core\Integration\Edd;

use StockResource\Contracts\Dto\OrderCompletedEvent;
use StockResource\Contracts\Dto\OrderRefundedEvent;
use StockResource\Contracts\Value\Money;
use StockResource\Contracts\Value\PositiveId;
use StockResource\Contracts\Value\UtcDateTime;

/**
 * Boundary for EDD 3.6.9 public API touchpoints:
 * EDD\Orders\Order, edd_get_order, edd_get_order_items, edd_get_customer.
 */
final readonly class EddOrderAdapter
{
    /**
     * @param  array<string, mixed>  $fixture
     */
    public function __construct(private array $fixture) {}

    public function getOrder(int $orderId): EddOrderSnapshot
    {
        $order = $this->fixture['orders'][$orderId] ?? null;
        if (! is_array($order)) {
            throw EddAdapterException::notFound('order', $orderId);
        }

        $customer = $this->getCustomer((int) ($order['customer_id'] ?? 0));

        return new EddOrderSnapshot(
            id: (int) $order['id'],
            type: trim((string) ($order['type'] ?? 'sale')),
            status: trim((string) ($order['status'] ?? '')),
            subtotal: $this->money($order['subtotal'] ?? '0'),
            tax: $this->money($order['tax'] ?? '0'),
            total: $this->money($order['total'] ?? '0'),
            currency: trim((string) ($order['currency'] ?? '')),
            customer: $customer,
            createdAt: trim((string) ($order['date_created'] ?? '')),
            completedAt: trim((string) ($order['date_completed'] ?? '')),
        );
    }

    public function getCustomer(int $customerId): EddCustomerSnapshot
    {
        $customer = $this->fixture['customers'][$customerId] ?? null;
        if (! is_array($customer)) {
            throw EddAdapterException::notFound('customer', $customerId);
        }

        return new EddCustomerSnapshot(
            id: (int) $customer['id'],
            userId: max(0, (int) ($customer['user_id'] ?? 0)),
            email: trim((string) ($customer['email'] ?? '')),
            name: trim((string) ($customer['name'] ?? '')),
        );
    }

    /**
     * @return list<EddOrderItemSnapshot>
     */
    public function getItems(int $orderId): array
    {
        $items = $this->fixture['items'][$orderId] ?? null;
        if (! is_array($items)) {
            throw EddAdapterException::notFound('order_items', $orderId);
        }

        return array_map(fn (array $item): EddOrderItemSnapshot => new EddOrderItemSnapshot(
            id: (int) $item['id'],
            orderId: (int) $item['order_id'],
            downloadId: (int) $item['product_id'],
            priceId: max(0, (int) ($item['price_id'] ?? 0)),
            quantity: (int) $item['quantity'],
            subtotal: $this->money($item['subtotal'] ?? '0'),
            tax: $this->money($item['tax'] ?? '0'),
            total: $this->money($item['total'] ?? '0'),
            businessSnapshot: is_array($item['snapshot'] ?? null) ? $item['snapshot'] : [],
        ), array_values($items));
    }

    public function shouldProjectCompletedOrder(int $orderId, string $oldStatus, string $newStatus): bool
    {
        if ($orderId < 1) {
            return false;
        }

        return $oldStatus !== 'complete' && $newStatus === 'complete';
    }

    public function completedEvent(int $orderId, string $completedAt): OrderCompletedEvent
    {
        $order = $this->getOrder($orderId);

        return new OrderCompletedEvent(
            orderId: PositiveId::fromInt($order->id),
            customerId: PositiveId::fromInt($order->customer->id),
            completedAt: UtcDateTime::fromString($completedAt),
            orderItemIds: array_map(
                static fn (EddOrderItemSnapshot $item): PositiveId => PositiveId::fromInt($item->id),
                $this->getItems($orderId),
            ),
        );
    }

    public function refundedEvent(int $saleOrderId, int $refundOrderId, bool $fullRefund, string $refundedAt): OrderRefundedEvent
    {
        $this->getOrder($saleOrderId);
        $refundOrder = $this->getOrder($refundOrderId);
        if ($refundOrder->type !== 'refund') {
            throw EddAdapterException::invalidShape('order', 'refund order type is required');
        }

        return new OrderRefundedEvent(
            orderId: PositiveId::fromInt($saleOrderId),
            refundedAt: UtcDateTime::fromString($refundedAt),
            refundedOrderItemIds: array_map(
                static fn (EddOrderItemSnapshot $item): PositiveId => PositiveId::fromInt($item->id),
                $this->getItems($refundOrderId),
            ),
            fullRefund: $fullRefund,
        );
    }

    private function money(mixed $value): Money
    {
        $amount = trim((string) $value);
        if (str_starts_with($amount, '-')) {
            $amount = substr($amount, 1);
        }

        if (! str_contains($amount, '.')) {
            return Money::fromString($amount);
        }

        [$whole, $decimal] = explode('.', $amount, 2);
        $decimal = rtrim($decimal, '0');
        $decimal = $decimal === '' ? '00' : substr(str_pad($decimal, 2, '0'), 0, 2);

        return Money::fromString($whole.'.'.$decimal);
    }
}
