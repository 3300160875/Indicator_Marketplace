<?php

declare(strict_types=1);

use StockResource\Core\Commerce\AccessMode;
use StockResource\Core\Commerce\CommerceException;
use StockResource\Core\Commerce\DiscountPolicy;
use StockResource\Core\Commerce\OrderItemSnapshotFactory;
use StockResource\Core\Commerce\PriceBook;
use StockResource\Core\Commerce\ProductType;
use StockResource\Core\Commerce\ResourcePurchaseRequest;
use StockResource\Core\Commerce\ResourcePurchaseValidator;

$root = dirname(__DIR__, 3);
$core = $root.'/packages/sr-core';
$contracts = $root.'/packages/sr-contracts';

foreach ([
    '/Exception/ContractException.php',
    '/Exception/ValidationException.php',
    '/Value/Money.php',
] as $sourceFile) {
    require_once $contracts.'/src'.$sourceFile;
}

foreach ([
    '/src/Commerce/AccessMode.php',
    '/src/Commerce/ProductType.php',
    '/src/Commerce/CommerceException.php',
    '/src/Commerce/PriceQuote.php',
    '/src/Commerce/PriceBook.php',
    '/src/Commerce/DiscountPolicy.php',
    '/src/Commerce/ResourcePurchaseRequest.php',
    '/src/Commerce/ResourcePurchaseValidation.php',
    '/src/Commerce/ResourcePurchaseValidator.php',
    '/src/Commerce/OrderItemSnapshotFactory.php',
] as $sourceFile) {
    require_once $core.$sourceFile;
}

function sr032_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr032_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr032_expect_error(string $codeName, callable $callback): void
{
    try {
        $callback();
    } catch (CommerceException $exception) {
        sr032_same($codeName, $exception->codeName, 'commerce exception code');

        return;
    }

    throw new RuntimeException('Expected commerce exception '.$codeName);
}

$priceBook = new PriceBook([
    1001 => [
        0 => '39.00',
        2 => '59.00',
    ],
    9001 => [
        0 => '399.00',
    ],
]);
$discounts = new DiscountPolicy([
    'SAVE10' => ['percent' => 10, 'applies_to' => [1001]],
    'VIPONLY' => ['percent' => 20, 'applies_to' => [9001]],
]);
$validator = new ResourcePurchaseValidator($priceBook, $discounts);

$meta = [
    '_sr_product_type' => 'resource',
    '_sr_access_mode' => 'purchase_or_vip',
    '_sr_current_version_id' => 501,
    '_sr_rights_status' => 'approved',
];
$request = new ResourcePurchaseRequest(
    downloadId: 1001,
    productType: ProductType::Resource,
    accessMode: AccessMode::PurchaseOrVip,
    priceId: 2,
    quantity: 2,
    clientUnitAmount: '1.00',
    discountCode: 'SAVE10',
    resourceMeta: $meta,
);
$validation = $validator->validate($request);
sr032_same('118.00', $validation->subtotal->toString(), 'subtotal is recalculated server-side');
sr032_same('11.80', $validation->discountAmount->toString(), 'discount is recalculated server-side');
sr032_same('106.20', $validation->total->toString(), 'total ignores tampered client amount');
sr032_same('SERVER_RECALCULATED', $validation->priceSource, 'price source is server recalculated');
sr032_same('purchase_or_vip', $validation->accessMode->value, 'controlled access mode is preserved');

$snapshot = OrderItemSnapshotFactory::fromValidation($validation, rulesVersion: 'v1');
sr032_same('resource', $snapshot['product_type'], 'snapshot product type');
sr032_same(1001, $snapshot['resource_id'], 'snapshot resource id');
sr032_same(501, $snapshot['version_id'], 'snapshot version id');
sr032_same(2, $snapshot['price_id'], 'snapshot price id');
sr032_same('59.00', $snapshot['unit_amount'], 'snapshot unit amount');
sr032_same('106.20', $snapshot['total_amount'], 'snapshot total amount');
sr032_same('purchase_or_vip', $snapshot['access_mode'], 'snapshot access mode');
sr032_same('v1', $snapshot['rules_version'], 'snapshot rules version');
sr032_assert(isset($snapshot['calculated_at']), 'snapshot records calculated timestamp');

$free = $validator->validate(new ResourcePurchaseRequest(
    downloadId: 1002,
    productType: ProductType::Resource,
    accessMode: AccessMode::Free,
    priceId: 0,
    quantity: 1,
    clientUnitAmount: '999.00',
    discountCode: null,
    resourceMeta: [
        '_sr_product_type' => 'resource',
        '_sr_access_mode' => 'free',
        '_sr_current_version_id' => 601,
        '_sr_rights_status' => 'approved',
    ],
));
sr032_same('0', $free->total->toString(), 'free resources recalculate to zero');

$vip = $validator->validate(new ResourcePurchaseRequest(
    downloadId: 1003,
    productType: ProductType::Resource,
    accessMode: AccessMode::Vip,
    priceId: 0,
    quantity: 1,
    clientUnitAmount: '88.00',
    discountCode: null,
    resourceMeta: [
        '_sr_product_type' => 'resource',
        '_sr_access_mode' => 'vip',
        '_sr_current_version_id' => 602,
        '_sr_rights_status' => 'approved',
    ],
));
sr032_same('0', $vip->total->toString(), 'vip resources do not create purchase amount');

$unavailable = $validator->validate(new ResourcePurchaseRequest(
    downloadId: 1004,
    productType: ProductType::Resource,
    accessMode: AccessMode::Unavailable,
    priceId: 0,
    quantity: 1,
    clientUnitAmount: '88.00',
    discountCode: null,
    resourceMeta: [
        '_sr_product_type' => 'resource',
        '_sr_access_mode' => 'unavailable',
        '_sr_current_version_id' => 603,
        '_sr_rights_status' => 'approved',
    ],
));
sr032_assert(! $unavailable->purchasable, 'unavailable resources cannot be purchased');
sr032_same('0', $unavailable->total->toString(), 'unavailable resources do not produce payable amount');

sr032_expect_error('product_type_mismatch', fn () => $validator->validate(new ResourcePurchaseRequest(
    downloadId: 9001,
    productType: ProductType::Resource,
    accessMode: AccessMode::Purchase,
    priceId: 0,
    quantity: 1,
    clientUnitAmount: '399.00',
    discountCode: null,
    resourceMeta: [
        '_sr_product_type' => 'membership_plan',
        '_sr_access_mode' => 'purchase',
        '_sr_current_version_id' => 0,
        '_sr_rights_status' => 'approved',
    ],
)));

sr032_expect_error('product_type_mismatch', fn () => $validator->validate(new ResourcePurchaseRequest(
    downloadId: 9001,
    productType: ProductType::MembershipPlan,
    accessMode: AccessMode::Purchase,
    priceId: 0,
    quantity: 1,
    clientUnitAmount: '399.00',
    discountCode: null,
    resourceMeta: [
        '_sr_product_type' => 'membership_plan',
        '_sr_access_mode' => 'purchase',
        '_sr_current_version_id' => 0,
        '_sr_rights_status' => 'approved',
    ],
)));

sr032_expect_error('access_mode_mismatch', fn () => $validator->validate(new ResourcePurchaseRequest(
    downloadId: 1001,
    productType: ProductType::Resource,
    accessMode: AccessMode::Purchase,
    priceId: 0,
    quantity: 1,
    clientUnitAmount: '39.00',
    discountCode: null,
    resourceMeta: $meta,
)));

sr032_expect_error('discount_not_applicable', fn () => $validator->validate(new ResourcePurchaseRequest(
    downloadId: 1001,
    productType: ProductType::Resource,
    accessMode: AccessMode::Purchase,
    priceId: 0,
    quantity: 1,
    clientUnitAmount: '39.00',
    discountCode: 'VIPONLY',
    resourceMeta: [
        '_sr_product_type' => 'resource',
        '_sr_access_mode' => 'purchase',
        '_sr_current_version_id' => 501,
        '_sr_rights_status' => 'approved',
    ],
)));

sr032_expect_error('invalid_quantity', fn () => $validator->validate(new ResourcePurchaseRequest(
    downloadId: 1001,
    productType: ProductType::Resource,
    accessMode: AccessMode::Purchase,
    priceId: 0,
    quantity: 0,
    clientUnitAmount: '39.00',
    discountCode: null,
    resourceMeta: [
        '_sr_product_type' => 'resource',
        '_sr_access_mode' => 'purchase',
        '_sr_current_version_id' => 501,
        '_sr_rights_status' => 'approved',
    ],
)));

sr032_expect_error('price_required', fn () => $validator->validate(new ResourcePurchaseRequest(
    downloadId: 1999,
    productType: ProductType::Resource,
    accessMode: AccessMode::Purchase,
    priceId: 0,
    quantity: 1,
    clientUnitAmount: '39.00',
    discountCode: null,
    resourceMeta: [
        '_sr_product_type' => 'resource',
        '_sr_access_mode' => 'purchase',
        '_sr_current_version_id' => 501,
        '_sr_rights_status' => 'approved',
    ],
)));

foreach (['free', 'purchase', 'vip', 'purchase_or_vip', 'unavailable'] as $mode) {
    sr032_same($mode, AccessMode::from($mode)->value, 'controlled access mode exists: '.$mode);
}

$source = '';
foreach (glob($core.'/src/Commerce/*.php') ?: [] as $file) {
    $source .= (string) file_get_contents($file)."\n";
}
foreach (['$_POST', '$_REQUEST', 'edd_get_price', 'wpdb', 'SELECT '] as $forbidden) {
    sr032_assert(! str_contains($source, $forbidden), 'commerce validation does not trust runtime request or direct EDD/db access: '.$forbidden);
}

echo "SR-032 commerce access and price checks passed.\n";
