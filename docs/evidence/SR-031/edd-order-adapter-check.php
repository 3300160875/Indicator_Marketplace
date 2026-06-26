<?php

declare(strict_types=1);

use StockResource\Contracts\Dto\OrderCompletedEvent;
use StockResource\Contracts\Dto\OrderRefundedEvent;
use StockResource\Contracts\Exception\ValidationException;
use StockResource\Contracts\Value\Money;
use StockResource\Core\Integration\Edd\EddCompatibilityFixtures;
use StockResource\Core\Integration\Edd\EddOrderAdapter;
use StockResource\Core\Integration\Edd\EddOrderItemSnapshot;

$root = dirname(__DIR__, 3);
$core = $root.'/packages/sr-core';
$contracts = $root.'/packages/sr-contracts';

foreach ([
    '/Exception/ContractException.php',
    '/Exception/ValidationException.php',
    '/Value/PositiveId.php',
    '/Value/Money.php',
    '/Value/UtcDateTime.php',
    '/Dto/OrderCompletedEvent.php',
    '/Dto/OrderRefundedEvent.php',
] as $sourceFile) {
    require_once $contracts.'/src'.$sourceFile;
}

foreach ([
    '/src/Integration/Edd/EddAdapterException.php',
    '/src/Integration/Edd/EddCompatibilityFixtures.php',
    '/src/Integration/Edd/EddCustomerSnapshot.php',
    '/src/Integration/Edd/EddOrderSnapshot.php',
    '/src/Integration/Edd/EddOrderItemSnapshot.php',
    '/src/Integration/Edd/EddOrderAdapter.php',
] as $sourceFile) {
    require_once $core.$sourceFile;
}

function sr031_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr031_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

$adapter = new EddOrderAdapter(EddCompatibilityFixtures::edd369());

$sale = $adapter->getOrder(1);
sr031_same(1, $sale->id, 'sale order id');
sr031_same('sale', $sale->type, 'sale order type');
sr031_same('complete', $sale->status, 'sale order status');
sr031_same('12.34', $sale->total->toString(), 'sale total is normalized money');
sr031_same(20, $sale->customer->id, 'customer id is projected');
sr031_same('buyer@example.test', $sale->customer->email, 'customer email is projected');

$items = $adapter->getItems(1);
sr031_same(1, count($items), 'sale has one item');
sr031_assert($items[0] instanceof EddOrderItemSnapshot, 'item snapshot type');
sr031_same(101, $items[0]->id, 'item id');
sr031_same(10, $items[0]->downloadId, 'download id');
sr031_same(1, $items[0]->quantity, 'quantity');
sr031_same('12.34', $items[0]->total->toString(), 'item total');
sr031_same('resource', $items[0]->businessSnapshot['product_type'], 'business snapshot product type');
sr031_same(1001, $items[0]->businessSnapshot['resource_id'], 'business snapshot resource id');

$completedEvent = $adapter->completedEvent(1, '2026-06-25T06:34:30Z');
sr031_assert($completedEvent instanceof OrderCompletedEvent, 'completed event type');
sr031_same([
    'order_id' => 1,
    'customer_id' => 20,
    'completed_at' => '2026-06-25T06:34:30Z',
    'order_item_ids' => [101],
], $completedEvent->toArray(), 'completed event serializes stable contract shape');
sr031_assert($adapter->shouldProjectCompletedOrder(1, 'pending', 'complete'), 'first complete transition projects');
sr031_assert(! $adapter->shouldProjectCompletedOrder(1, 'complete', 'complete'), 'duplicate complete transition is ignored');

$fullRefund = $adapter->refundedEvent(saleOrderId: 1, refundOrderId: 2, fullRefund: true, refundedAt: '2026-06-25T06:40:00Z');
sr031_assert($fullRefund instanceof OrderRefundedEvent, 'full refund event type');
sr031_same([
    'order_id' => 1,
    'refunded_at' => '2026-06-25T06:40:00Z',
    'refunded_order_item_ids' => [201],
    'full_refund' => true,
], $fullRefund->toArray(), 'full refund serializes stable contract shape');

$partialRefund = $adapter->refundedEvent(saleOrderId: 3, refundOrderId: 4, fullRefund: false, refundedAt: '2026-06-25T06:45:00Z');
sr031_same([
    'order_id' => 3,
    'refunded_at' => '2026-06-25T06:45:00Z',
    'refunded_order_item_ids' => [401],
    'full_refund' => false,
], $partialRefund->toArray(), 'partial item refund serializes stable contract shape');
sr031_same('3.00', $adapter->getItems(4)[0]->total->toString(), 'refund item total is normalized to absolute money');
sr031_same(-1, $adapter->getItems(4)[0]->quantity, 'refund item keeps negative quantity from EDD fixture');

$customer = $adapter->getCustomer(30);
sr031_same(30, $customer->id, 'customer lookup id');
sr031_same('partial@example.test', $customer->email, 'customer lookup email');

try {
    Money::fromString('-1.00');
    throw new RuntimeException('Money contract should reject negative EDD totals');
} catch (ValidationException) {
    // EddOrderAdapter normalizes refund item totals before creating Money values.
}

$adapterSource = '';
foreach (glob($core.'/src/Integration/Edd/*.php') ?: [] as $file) {
    $adapterSource .= (string) file_get_contents($file)."\n";
}
foreach (['EDD\\Orders\\Order', 'edd_get_order', 'edd_get_order_items', 'edd_get_customer'] as $needle) {
    sr031_assert(str_contains($adapterSource, $needle), 'adapter boundary documents EDD API touchpoint: '.$needle);
}

$outsideSource = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($core.'/src')) as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/Integration/Edd/')) {
        continue;
    }
    $outsideSource .= (string) file_get_contents($file->getPathname())."\n";
}
foreach (['EDD\\', 'edd_get_order', 'edd_get_order_items', 'edd_get_customer'] as $forbidden) {
    sr031_assert(! str_contains($outsideSource, $forbidden), 'EDD API is not scattered outside Integration/Edd: '.$forbidden);
}

echo "SR-031 EDD order adapter checks passed.\n";
