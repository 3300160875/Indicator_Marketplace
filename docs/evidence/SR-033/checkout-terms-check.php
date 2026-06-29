<?php

declare(strict_types=1);

use StockResource\PaymentGateways\Checkout\CheckoutException;
use StockResource\PaymentGateways\Checkout\CheckoutOrderCreator;
use StockResource\PaymentGateways\Checkout\CheckoutPolicy;
use StockResource\PaymentGateways\Checkout\CheckoutRequest;
use StockResource\PaymentGateways\Checkout\CheckoutSnapshotFactory;
use StockResource\PaymentGateways\Checkout\CheckoutTerms;

$root = dirname(__DIR__, 3);
$gateway = $root.'/packages/sr-payment-gateways';
$theme = $root.'/web/app/themes/stock-resource-theme';

foreach ([
    '/src/Checkout/CheckoutException.php',
    '/src/Checkout/CheckoutTerms.php',
    '/src/Checkout/CheckoutRequest.php',
    '/src/Checkout/CheckoutPolicy.php',
    '/src/Checkout/CheckoutSnapshotFactory.php',
    '/src/Checkout/CheckoutOrderCreator.php',
] as $sourceFile) {
    require_once $gateway.$sourceFile;
}

function sr033_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr033_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr033_expect_error(string $codeName, callable $callback): void
{
    try {
        $callback();
    } catch (CheckoutException $exception) {
        sr033_same($codeName, $exception->codeName, 'checkout exception code');

        return;
    }

    throw new RuntimeException('Expected checkout exception '.$codeName);
}

$terms = new CheckoutTerms(
    serviceTermsVersion: 'terms-2026-06',
    digitalDeliveryVersion: 'digital-delivery-2026-06',
    privacyVersion: 'privacy-2026-06',
    refundRuleVersion: 'refund-2026-06',
);

$request = new CheckoutRequest(
    userId: 77,
    returnUrl: 'https://example.test/downloads/awesome-indicator/',
    serverTotal: '106.20',
    clientTotal: '1.00',
    currency: 'CNY',
    termsAccepted: true,
    digitalDeliveryAccepted: true,
    terms: $terms,
    lineItems: [
        [
            'download_id' => 1001,
            'resource_id' => 1001,
            'version_id' => 501,
            'price_id' => 2,
            'access_mode' => 'purchase_or_vip',
            'quantity' => 2,
            'unit_amount' => '59.00',
            'total_amount' => '106.20',
        ],
    ],
);

$loginPolicy = new CheckoutPolicy(
    manualPaymentEnabled: true,
    gate0Approved: true,
    loginBaseUrl: '/wp-login.php',
);
$guestDecision = $loginPolicy->guestDecision('/checkout/?download_id=1001');
sr033_same(false, $guestDecision['allowed'], 'guest checkout is not allowed');
sr033_same('login_required', $guestDecision['reason'], 'guest checkout reason is explicit');
sr033_same('/wp-login.php?redirect_to=%2Fcheckout%2F%3Fdownload_id%3D1001', $guestDecision['login_url'], 'guest checkout redirects back after login');

$disabledPolicy = new CheckoutPolicy(
    manualPaymentEnabled: false,
    gate0Approved: false,
    loginBaseUrl: '/wp-login.php',
);
$createdOrders = 0;
$disabledCreator = new CheckoutOrderCreator($disabledPolicy, new CheckoutSnapshotFactory);
$enabledCreator = new CheckoutOrderCreator($loginPolicy, new CheckoutSnapshotFactory);
sr033_expect_error('payment_disabled', function () use ($disabledCreator, $request, &$createdOrders): void {
    $disabledCreator->create($request, static function (array $snapshot) use (&$createdOrders): array {
        $createdOrders++;

        return ['id' => 999, 'snapshot' => $snapshot];
    });
});
sr033_same(0, $createdOrders, 'payment disabled must not call real EDD order creation');

$manualDisabledCreator = new CheckoutOrderCreator(new CheckoutPolicy(
    manualPaymentEnabled: false,
    gate0Approved: true,
    loginBaseUrl: '/wp-login.php',
), new CheckoutSnapshotFactory);
sr033_expect_error('payment_disabled', function () use ($manualDisabledCreator, $request, &$createdOrders): void {
    $manualDisabledCreator->create($request, static function (array $snapshot) use (&$createdOrders): array {
        $createdOrders++;

        return ['id' => 998, 'snapshot' => $snapshot];
    });
});
sr033_same(0, $createdOrders, 'manual payment disabled must not call real EDD order creation');

$gate0DisabledCreator = new CheckoutOrderCreator(new CheckoutPolicy(
    manualPaymentEnabled: true,
    gate0Approved: false,
    loginBaseUrl: '/wp-login.php',
), new CheckoutSnapshotFactory);
sr033_expect_error('payment_disabled', function () use ($gate0DisabledCreator, $request, &$createdOrders): void {
    $gate0DisabledCreator->create($request, static function (array $snapshot) use (&$createdOrders): array {
        $createdOrders++;

        return ['id' => 997, 'snapshot' => $snapshot];
    });
});
sr033_same(0, $createdOrders, 'Gate 0 disabled must not call real EDD order creation');

$guestRequest = new CheckoutRequest(
    userId: null,
    returnUrl: $request->returnUrl,
    serverTotal: $request->serverTotal,
    clientTotal: $request->clientTotal,
    currency: $request->currency,
    termsAccepted: true,
    digitalDeliveryAccepted: true,
    terms: $terms,
    lineItems: $request->lineItems,
);
sr033_expect_error('login_required', function () use ($enabledCreator, $guestRequest, &$createdOrders): void {
    $enabledCreator->create($guestRequest, static function (array $snapshot) use (&$createdOrders): array {
        $createdOrders++;

        return ['id' => 996, 'snapshot' => $snapshot];
    });
});
sr033_same(0, $createdOrders, 'guest create must not call real EDD order creation');

$created = $enabledCreator->create($request, static function (array $snapshot) use (&$createdOrders): array {
    $createdOrders++;

    return ['id' => 1234, 'snapshot' => $snapshot];
});
sr033_same(1, $createdOrders, 'enabled checkout calls order creation once');
sr033_same(1234, $created['id'], 'order creator returns EDD order result');
sr033_same('106.20', $created['snapshot']['order_amount'], 'snapshot uses server total, not client amount');
sr033_same('CNY', $created['snapshot']['currency'], 'snapshot records settlement currency');
sr033_same('SERVER_RECALCULATED', $created['snapshot']['amount_source'], 'snapshot records server recalculation source');
sr033_same('1.00', $created['snapshot']['client_amount_ignored'], 'snapshot records ignored client amount for audit');
sr033_same('terms-2026-06', $created['snapshot']['terms_snapshot']['service_terms_version'], 'snapshot records terms version');
sr033_same('digital-delivery-2026-06', $created['snapshot']['terms_snapshot']['digital_delivery_version'], 'snapshot records digital delivery version');
sr033_same('privacy-2026-06', $created['snapshot']['terms_snapshot']['privacy_version'], 'snapshot records privacy version');
sr033_same('refund-2026-06', $created['snapshot']['terms_snapshot']['refund_rule_version'], 'snapshot records refund version');
sr033_assert(isset($created['snapshot']['terms_snapshot']['confirmed_at']), 'snapshot records confirmation timestamp');
sr033_same(1001, $created['snapshot']['line_items'][0]['resource_id'], 'snapshot preserves line item resource id');

sr033_expect_error('terms_not_accepted', fn () => $enabledCreator->create(new CheckoutRequest(
    userId: 77,
    returnUrl: $request->returnUrl,
    serverTotal: $request->serverTotal,
    clientTotal: $request->clientTotal,
    currency: $request->currency,
    termsAccepted: false,
    digitalDeliveryAccepted: true,
    terms: $terms,
    lineItems: $request->lineItems,
), static fn (array $snapshot): array => ['id' => 1, 'snapshot' => $snapshot]));

sr033_expect_error('digital_delivery_not_accepted', fn () => $enabledCreator->create(new CheckoutRequest(
    userId: 77,
    returnUrl: $request->returnUrl,
    serverTotal: $request->serverTotal,
    clientTotal: $request->clientTotal,
    currency: $request->currency,
    termsAccepted: true,
    digitalDeliveryAccepted: false,
    terms: $terms,
    lineItems: $request->lineItems,
), static fn (array $snapshot): array => ['id' => 1, 'snapshot' => $snapshot]));

$template = (string) file_get_contents($theme.'/edd_templates/checkout-terms.php');
foreach ([
    'sr-checkout-terms',
    'terms_snapshot',
    'digital_delivery_version',
    'refund_rule_version',
    'required',
] as $needle) {
    sr033_assert(str_contains($template, $needle), 'checkout terms template contains '.$needle);
}
foreach (['$_POST', 'edd_insert_payment', 'edd_update_payment_status', 'wpdb', 'SELECT '] as $forbidden) {
    sr033_assert(! str_contains($template, $forbidden), 'checkout template avoids direct payment mutation or SQL: '.$forbidden);
}

echo "SR-033 checkout terms checks passed.\n";
