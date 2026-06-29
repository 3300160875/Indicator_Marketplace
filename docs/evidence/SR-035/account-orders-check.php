<?php

declare(strict_types=1);

use StockResource\Core\Account\Orders\AccountOrderAccessException;
use StockResource\Core\Account\Orders\AccountOrderProjectionService;
use StockResource\Core\Account\Orders\AccountOrderRepository;

$root = dirname(__DIR__, 3);
$core = $root.'/packages/sr-core';

foreach ([
    '/src/Account/Orders/AccountOrderAccessException.php',
    '/src/Account/Orders/AccountOrderRepository.php',
    '/src/Account/Orders/AccountOrderProjectionService.php',
] as $sourceFile) {
    require_once $core.$sourceFile;
}

function sr035_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr035_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr035_expect_error(string $codeName, callable $callback): void
{
    try {
        $callback();
    } catch (AccountOrderAccessException $exception) {
        sr035_same($codeName, $exception->codeName, 'account orders exception code');

        return;
    }

    throw new RuntimeException('Expected account orders exception '.$codeName);
}

$orders = [
    [
        'order_id' => 1,
        'user_id' => 200,
        'status' => 'complete',
        'total_amount' => '106.20',
        'currency' => 'CNY',
        'created_at' => '2026-06-25T06:30:00Z',
        'completed_at' => '2026-06-25T06:34:30Z',
        'internal_review_note' => 'manual reviewer matched bank reference 123',
        'items' => [
            [
                'title' => '趋势指标源码包',
                'product_type' => 'resource',
                'resource_id' => 1001,
                'version_id' => 501,
                'download_status' => 'available',
                'quota' => ['used' => 2, 'limit' => 10, 'reset_at' => '2026-06-26T00:00:00Z'],
            ],
        ],
    ],
    [
        'order_id' => 2,
        'user_id' => 200,
        'status' => 'expired',
        'total_amount' => '39.00',
        'currency' => 'CNY',
        'created_at' => '2026-06-20T06:30:00Z',
        'completed_at' => '',
        'internal_review_note' => 'expired after manual payment timeout',
        'items' => [
            [
                'title' => '历史策略资源',
                'product_type' => 'resource',
                'resource_id' => 1002,
                'version_id' => 502,
                'download_status' => 'expired',
                'quota' => ['used' => 0, 'limit' => 3, 'reset_at' => '2026-06-21T00:00:00Z'],
            ],
        ],
    ],
    [
        'order_id' => 3,
        'user_id' => 200,
        'status' => 'revoked',
        'total_amount' => '0',
        'currency' => 'CNY',
        'created_at' => '2026-06-18T06:30:00Z',
        'completed_at' => '2026-06-18T06:35:00Z',
        'internal_review_note' => 'fraud review details must stay internal',
        'items' => [
            [
                'title' => '撤权资源',
                'product_type' => 'resource',
                'resource_id' => 1003,
                'version_id' => 503,
                'download_status' => 'revoked',
                'quota' => ['used' => 10, 'limit' => 10, 'reset_at' => '2026-06-19T00:00:00Z'],
            ],
        ],
    ],
    [
        'order_id' => 4,
        'user_id' => 300,
        'status' => 'complete',
        'total_amount' => '8.00',
        'currency' => 'CNY',
        'created_at' => '2026-06-26T06:30:00Z',
        'completed_at' => '2026-06-26T06:34:30Z',
        'internal_review_note' => 'belongs to another user',
        'items' => [
            [
                'title' => '其他用户资源',
                'product_type' => 'resource',
                'resource_id' => 2001,
                'version_id' => 601,
                'download_status' => 'available',
                'quota' => ['used' => 1, 'limit' => 1, 'reset_at' => '2026-06-27T00:00:00Z'],
            ],
        ],
    ],
];

$repository = new AccountOrderRepository($orders);
$service = new AccountOrderProjectionService($repository);

$cacheKey = $service->cacheKey(userId: 200, rulesVersion: 'account-orders-v1');
sr035_same('account_orders:200:account-orders-v1', $cacheKey, 'cache key includes user and rules version');
sr035_same('account_orders:200:account-orders-v2', $service->cacheKey(userId: 200, rulesVersion: 'account-orders-v2'), 'cache key changes after rules version changes');

$projection = $service->forUser(userId: 200, rulesVersion: 'account-orders-v1');
sr035_same('ready', $projection['state'], 'user with orders gets ready state');
sr035_same('account_orders:200:account-orders-v1', $projection['cache_key'], 'projection exposes scoped cache key');
sr035_same(3, count($projection['orders']), 'only current user orders are returned');
sr035_same([1, 2, 3], array_column($projection['orders'], 'order_id'), 'other users orders are filtered out');

$first = $projection['orders'][0];
sr035_same('已完成', $first['status_label'], 'complete status maps to readable label');
sr035_same('可下载', $first['items'][0]['download_label'], 'available download maps to readable label');
sr035_same('2026-06-26T00:00:00Z', $first['items'][0]['quota_reset_at'], 'quota reset is exposed');
sr035_same('2/10', $first['items'][0]['quota_label'], 'quota usage is readable');
sr035_same(false, array_key_exists('internal_review_note', $first), 'internal review notes are not exposed');
sr035_same(false, str_contains(json_encode($projection, JSON_THROW_ON_ERROR), 'manual reviewer'), 'projection does not leak internal notes');

$expired = $projection['orders'][1];
sr035_same('已过期', $expired['status_label'], 'expired status maps to readable label');
sr035_same('已过期', $expired['items'][0]['download_label'], 'expired download maps to readable label');

$revoked = $projection['orders'][2];
sr035_same('已撤权', $revoked['status_label'], 'revoked status maps to readable label');
sr035_same('已撤权', $revoked['items'][0]['download_label'], 'revoked download maps to readable label');
sr035_same('2026-06-19T00:00:00Z', $revoked['items'][0]['quota_reset_at'], 'revoked item still exposes quota reset for support context');

$empty = $service->forUser(userId: 999, rulesVersion: 'account-orders-v1');
sr035_same('empty', $empty['state'], 'users without orders get empty state');
sr035_same([], $empty['orders'], 'empty state returns no orders');

sr035_expect_error('login_required', fn () => $service->forUser(userId: 0, rulesVersion: 'account-orders-v1'));
sr035_expect_error('login_required', fn () => $service->cacheKey(userId: 0, rulesVersion: 'account-orders-v1'));

$source = '';
foreach (glob($core.'/src/Account/Orders/*.php') ?: [] as $file) {
    $source .= (string) file_get_contents($file)."\n";
}
foreach (['internal_review_note', 'wpdb', 'SELECT ', 'edd_get_order', '$_POST', '$_REQUEST'] as $forbidden) {
    sr035_assert(! str_contains($source, $forbidden), 'account order projection avoids internal notes, SQL, EDD runtime and request globals: '.$forbidden);
}

echo "SR-035 account order checks passed.\n";
