<?php

declare(strict_types=1);

use StockResource\Core\Commerce\OrderSnapshot\OrderItemBusinessSnapshot;
use StockResource\Core\Commerce\OrderSnapshot\OrderSnapshotException;
use StockResource\Core\Commerce\OrderSnapshot\OrderSnapshotService;
use StockResource\Core\Integration\Edd\EddCompatibilityFixtures;
use StockResource\Core\Integration\Edd\EddOrderAdapter;

$root = dirname(__DIR__, 3);
$core = $root.'/packages/sr-core';
$contracts = $root.'/packages/sr-contracts';

foreach ([
    '/Exception/ContractException.php',
    '/Exception/ValidationException.php',
    '/Value/Money.php',
    '/Value/PositiveId.php',
    '/Value/UtcDateTime.php',
] as $sourceFile) {
    require_once $contracts.'/src'.$sourceFile;
}

foreach ([
    '/src/Integration/Edd/EddAdapterException.php',
    '/src/Integration/Edd/EddCustomerSnapshot.php',
    '/src/Integration/Edd/EddOrderSnapshot.php',
    '/src/Integration/Edd/EddOrderItemSnapshot.php',
    '/src/Integration/Edd/EddCompatibilityFixtures.php',
    '/src/Integration/Edd/EddOrderAdapter.php',
    '/src/Commerce/OrderSnapshot/OrderSnapshotException.php',
    '/src/Commerce/OrderSnapshot/OrderItemBusinessSnapshot.php',
    '/src/Commerce/OrderSnapshot/OrderSnapshotService.php',
] as $sourceFile) {
    require_once $core.$sourceFile;
}

function sr034_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr034_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr034_expect_error(string $codeName, callable $callback): void
{
    try {
        $callback();
    } catch (OrderSnapshotException $exception) {
        sr034_same($codeName, $exception->codeName, 'order snapshot exception code');

        return;
    }

    throw new RuntimeException('Expected order snapshot exception '.$codeName);
}

$fixture = EddCompatibilityFixtures::edd369();
$fixture['items'][1][0]['snapshot'] = [
    'product_type' => 'resource',
    'resource_id' => 1001,
    'version_id' => 501,
    'access_mode' => 'purchase',
    'rules_version' => 'rules-2026-06',
    'price_id' => 2,
    'unit_amount' => '12.34',
    'total_amount' => '12.34',
    'terms_snapshot' => [
        'service_terms_version' => 'terms-2026-06',
        'digital_delivery_version' => 'digital-delivery-2026-06',
        'refund_rule_version' => 'refund-2026-06',
        'privacy_version' => 'privacy-2026-06',
    ],
];
$fixture['items'][3][0]['snapshot'] = [
    'product_type' => 'membership_plan',
    'plan_download_id' => 9001,
    'plan_code' => 'monthly_vip',
    'access_mode' => 'purchase',
    'rules_version' => 'rules-2026-06',
    'price_id' => 0,
    'unit_amount' => '8.00',
    'total_amount' => '8.00',
    'terms_snapshot' => [
        'service_terms_version' => 'terms-2026-06',
        'digital_delivery_version' => 'digital-delivery-2026-06',
        'refund_rule_version' => 'refund-2026-06',
        'privacy_version' => 'privacy-2026-06',
    ],
];
$fixture['customers'][40] = [
    'id' => 40,
    'user_id' => 0,
    'email' => 'legacy@example.test',
    'name' => 'Legacy Buyer',
];
$fixture['orders'][5] = [
    'id' => 5,
    'type' => 'sale',
    'status' => 'complete',
    'customer_id' => 40,
    'subtotal' => '1.000000000',
    'tax' => '0.000000000',
    'total' => '1.000000000',
    'currency' => 'CNY',
    'date_created' => '2026-06-25T07:00:00Z',
    'date_completed' => '2026-06-25T07:02:00Z',
];
$fixture['items'][5] = [
    [
        'id' => 501,
        'order_id' => 5,
        'product_id' => 12,
        'price_id' => 0,
        'quantity' => 1,
        'subtotal' => '1.000000000',
        'tax' => '0.000000000',
        'total' => '1.000000000',
        'snapshot' => [
            'product_type' => 'resource',
            'resource_id' => 1005,
            'rules_version' => 'rules-2026-06',
        ],
    ],
];

$adapter = new EddOrderAdapter($fixture);
$service = new OrderSnapshotService;

$snapshots = $service->snapshotsForOrder($adapter, orderId: 1, userId: 200);
sr034_same(1, count($snapshots), 'completed order produces one item snapshot');
$snapshot = $snapshots[0];
sr034_assert($snapshot instanceof OrderItemBusinessSnapshot, 'snapshot type');
sr034_same(1, $snapshot->orderId, 'snapshot freezes order id');
sr034_same(101, $snapshot->orderItemId, 'snapshot freezes order item id');
sr034_same(200, $snapshot->userId, 'snapshot freezes owning user id');
sr034_same('resource', $snapshot->productType, 'snapshot freezes product type');
sr034_same(1001, $snapshot->resourceId, 'snapshot freezes resource id');
sr034_same(501, $snapshot->versionId, 'snapshot freezes resource version id');
sr034_same(null, $snapshot->planDownloadId, 'resource snapshot does not invent plan id');
sr034_same('rules-2026-06', $snapshot->rulesVersion, 'snapshot freezes rules version');
sr034_same(2, $snapshot->priceId, 'snapshot freezes price id from business snapshot');
sr034_same('12.34', $snapshot->totalAmount, 'snapshot freezes item amount');
sr034_same('CNY', $snapshot->currency, 'snapshot freezes currency');
sr034_same('none', $snapshot->refundStatus, 'completed order has no refund status');
sr034_same('terms-2026-06', $snapshot->termsSnapshot['service_terms_version'], 'snapshot freezes terms version');
sr034_assert(strlen($snapshot->idempotencyKey) === 64, 'snapshot idempotency key is stable hash');

$repeat = $service->snapshotsForOrder($adapter, orderId: 1, userId: 200);
sr034_same($snapshot->idempotencyKey, $repeat[0]->idempotencyKey, 'repeated creation is idempotent');
sr034_same($snapshot->toArray(), $repeat[0]->toArray(), 'repeated creation returns same snapshot payload');

$changedFixture = $fixture;
$changedFixture['items'][1][0]['snapshot']['resource_id'] = 9999;
$changedFixture['items'][1][0]['snapshot']['rules_version'] = 'rules-2099';
$changedAdapter = new EddOrderAdapter($changedFixture);
$preserved = $service->snapshotsForOrder($changedAdapter, orderId: 1, userId: 200, existingByItemId: [
    101 => $snapshot,
]);
sr034_same(1001, $preserved[0]->resourceId, 'existing snapshot preserves historical resource id after product metadata changes');
sr034_same('rules-2026-06', $preserved[0]->rulesVersion, 'existing snapshot preserves historical rules version after product metadata changes');
sr034_same($snapshot->idempotencyKey, $preserved[0]->idempotencyKey, 'existing snapshot keeps idempotency key');

$planSnapshots = $service->snapshotsForOrder($adapter, orderId: 3, userId: 300);
sr034_same('membership_plan', $planSnapshots[0]->productType, 'plan snapshot freezes product type');
sr034_same(9001, $planSnapshots[0]->planDownloadId, 'plan snapshot freezes plan download id');
sr034_same('monthly_vip', $planSnapshots[0]->planCode, 'plan snapshot freezes plan code');
sr034_same('partial', $planSnapshots[0]->refundStatus, 'partially refunded order is marked partial');

sr034_expect_error('order_not_owned', fn () => $service->snapshotsForOrder($adapter, orderId: 1, userId: 999));
sr034_expect_error('missing_user_mapping', fn () => $service->snapshotsForOrder($adapter, orderId: 5, userId: 0));
sr034_expect_error('refund_order_not_accessible', fn () => $service->snapshotsForOrder($adapter, orderId: 2, userId: 200));

$source = '';
foreach (glob($core.'/src/Commerce/OrderSnapshot/*.php') ?: [] as $file) {
    $source .= (string) file_get_contents($file)."\n";
}
foreach (['wpdb', 'SELECT ', 'edd_get_order', 'edd_get_order_items', '$_POST', '$_REQUEST'] as $forbidden) {
    sr034_assert(! str_contains($source, $forbidden), 'order snapshot layer avoids direct SQL/request/EDD access: '.$forbidden);
}

echo "SR-034 order item snapshot checks passed.\n";
