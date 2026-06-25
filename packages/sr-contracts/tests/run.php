<?php
declare(strict_types=1);

use StockResource\Contracts\Dto\DownloadTokenResponse;
use StockResource\Contracts\Dto\EntitlementDto;
use StockResource\Contracts\Dto\ErrorResponse;
use StockResource\Contracts\Dto\OrderCompletedEvent;
use StockResource\Contracts\Dto\OrderRefundedEvent;
use StockResource\Contracts\Dto\Pagination;
use StockResource\Contracts\Enum\AccessSource;
use StockResource\Contracts\Enum\ErrorCode;
use StockResource\Contracts\Exception\ValidationException;
use StockResource\Contracts\Service\DownloadTokenIssuer;
use StockResource\Contracts\Service\EddOrderProjector;
use StockResource\Contracts\Value\Money;
use StockResource\Contracts\Value\PositiveId;
use StockResource\Contracts\Value\RequestId;
use StockResource\Contracts\Value\UtcDateTime;

$src = dirname(__DIR__) . '/src';
foreach ([
    '/Value/PositiveId.php',
    '/Value/Money.php',
    '/Value/RequestId.php',
    '/Value/UtcDateTime.php',
    '/Enum/AccessSource.php',
    '/Enum/ErrorCode.php',
    '/Exception/ContractException.php',
    '/Exception/ValidationException.php',
    '/Dto/Pagination.php',
    '/Dto/ErrorResponse.php',
    '/Dto/EntitlementDto.php',
    '/Dto/DownloadTokenResponse.php',
    '/Dto/OrderCompletedEvent.php',
    '/Dto/OrderRefundedEvent.php',
    '/Service/EddOrderProjector.php',
    '/Service/DownloadTokenIssuer.php',
] as $file) {
    require_once $src . $file;
}

function assert_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

function assert_throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (ValidationException) {
        return;
    }

    throw new RuntimeException($message);
}

assert_same(42, PositiveId::fromInt(42)->toInt(), 'positive id keeps integer value');
assert_throws(fn() => PositiveId::fromInt(0), 'positive id rejects zero');
assert_throws(fn() => PositiveId::fromInt(-1), 'positive id rejects negative values');

assert_same('39.00', Money::fromString('39.00')->toString(), 'money keeps two-decimal strings');
assert_same('0', Money::fromString('0')->toString(), 'money allows zero');
assert_throws(fn() => Money::fromString('01.00'), 'money rejects leading zeroes');
assert_throws(fn() => Money::fromString('1.234'), 'money rejects more than two decimals');
assert_throws(fn() => Money::fromString('-1.00'), 'money rejects negative values');

$requestId = RequestId::fromString('123e4567-e89b-12d3-a456-426614174000');
assert_same('123e4567-e89b-12d3-a456-426614174000', $requestId->toString(), 'request id serializes as uuid');
assert_throws(fn() => RequestId::fromString('not-a-uuid'), 'request id rejects invalid uuid');

$time = UtcDateTime::fromString('2026-06-25T06:45:00Z');
assert_same('2026-06-25T06:45:00Z', $time->toString(), 'UTC date-time serializes as canonical UTC');
assert_throws(fn() => UtcDateTime::fromString('2026-06-25 06:45:00'), 'UTC date-time rejects non ISO-8601 input');

$pagination = new Pagination(page: 2, perPage: 20, total: 41, totalPages: 3);
assert_same(['page' => 2, 'per_page' => 20, 'total' => 41, 'total_pages' => 3], $pagination->toArray(), 'pagination serializes openapi shape');
assert_throws(fn() => new Pagination(page: 1, perPage: 101, total: 0, totalPages: 0), 'pagination rejects per-page over 100');

$error = new ErrorResponse(
    errorCode: ErrorCode::QuotaExhausted,
    message: 'Quota exhausted',
    requestId: $requestId,
    fieldErrors: ['resource_id' => ['required']],
    retryAfter: 60,
    meta: ['remaining' => 0],
);
assert_same('QUOTA_EXHAUSTED', $error->toArray()['error_code'], 'error response serializes stable error code');
assert_same(60, $error->toArray()['retry_after'], 'error response includes retry_after');

$entitlement = new EntitlementDto(
    id: PositiveId::fromInt(7),
    grantType: 'membership',
    status: 'active',
    startsAt: $time,
    expiresAt: null,
    scope: ['type' => 'all'],
    quota: ['period' => 'month', 'limit' => 30],
    rulesVersion: 'v1',
    sourceOrderId: PositiveId::fromInt(88),
);
assert_same(7, $entitlement->toArray()['id'], 'entitlement serializes id');
assert_same(88, $entitlement->toArray()['source_order_id'], 'entitlement serializes nullable source order id');
assert_throws(fn() => new EntitlementDto(
    id: PositiveId::fromInt(1),
    grantType: 'unknown',
    status: 'active',
    startsAt: $time,
    expiresAt: null,
    scope: [],
    quota: null,
    rulesVersion: 'v1',
    sourceOrderId: null,
), 'entitlement rejects unknown grant type');

$download = new DownloadTokenResponse(
    requestId: $requestId,
    downloadUrl: '/wp-json/sr/v1/download/token',
    expiresAt: $time,
    accessSource: AccessSource::Vip,
    remainingQuota: 12,
);
assert_same('VIP', $download->toArray()['access_source'], 'download token serializes access source');
assert_throws(fn() => new DownloadTokenResponse($requestId, '', $time, AccessSource::Vip, null), 'download token rejects empty URL');

$completed = new OrderCompletedEvent(
    orderId: PositiveId::fromInt(100),
    customerId: PositiveId::fromInt(200),
    completedAt: $time,
    orderItemIds: [PositiveId::fromInt(300)],
);
assert_same([300], $completed->toArray()['order_item_ids'], 'completed event serializes order item ids');
assert_throws(fn() => new OrderCompletedEvent(PositiveId::fromInt(100), PositiveId::fromInt(200), $time, []), 'completed event rejects empty item list');

$refunded = new OrderRefundedEvent(
    orderId: PositiveId::fromInt(100),
    refundedAt: $time,
    refundedOrderItemIds: [PositiveId::fromInt(300)],
    fullRefund: false,
);
assert_same(false, $refunded->toArray()['full_refund'], 'refund event serializes full refund flag');

assert_true(interface_exists(EddOrderProjector::class), 'EDD projector interface exists');
assert_true(interface_exists(DownloadTokenIssuer::class), 'download token issuer interface exists');

$sourceFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));
foreach ($sourceFiles as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $contents = file_get_contents($file->getPathname());
    assert_true(! str_contains($contents, 'wp_'), 'contracts package must not call WordPress prefixed functions');
    assert_true(! str_contains($contents, 'add_action'), 'contracts package must not depend on WordPress hooks');
}

echo "sr-contracts tests: ok\n";
