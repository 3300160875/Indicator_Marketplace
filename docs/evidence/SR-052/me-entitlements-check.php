<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);

if (! function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args): bool
    {
        $GLOBALS['sr052_registered_route'] = [
            'namespace' => $namespace,
            'route' => $route,
            'args' => $args,
        ];

        return true;
    }
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return (int) ($GLOBALS['sr052_current_user_id'] ?? 0);
    }
}

if (! function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return get_current_user_id() > 0;
    }
}

require_once $root.'/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementStatus.php';
require_once $root.'/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementException.php';
require_once $root.'/packages/sr-entitlements/src/Infrastructure/Repository/Entitlement.php';
require_once $root.'/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementRepository.php';
require_once $root.'/packages/sr-entitlements/src/Infrastructure/Repository/InMemoryEntitlementRepository.php';
require_once $root.'/packages/sr-entitlements/src/Rest/Me/MeEntitlementsController.php';
require_once $root.'/web/app/themes/stock-resource-theme/templates/account/membership.php';

use StockResource\Entitlements\Infrastructure\Repository\Entitlement;
use StockResource\Entitlements\Infrastructure\Repository\InMemoryEntitlementRepository;
use StockResource\Entitlements\Rest\Me\InMemoryMeEntitlementsCacheStore;
use StockResource\Entitlements\Rest\Me\MeEntitlementsController;
use StockResource\Entitlements\Rest\Me\MeEntitlementsRouteRegistrar;

function sr052_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function sr052_entitlement(
    int $userId,
    string $grantType,
    string $planCode,
    string $startsAt,
    ?string $expiresAt,
    array $quota,
    int $priority = 10,
): Entitlement {
    return Entitlement::fromSnapshot(
        userId: $userId,
        eddCustomerId: 9000 + $userId,
        grantType: $grantType,
        sourceType: 'edd',
        sourceOrderId: 7000 + $userId,
        sourceOrderItemId: null,
        planDownloadId: $grantType === 'membership' ? 5001 : null,
        parentEntitlementId: null,
        resourceId: $grantType === 'purchase' ? 88 : null,
        scopeType: $grantType === 'purchase' ? 'resources' : 'all',
        scopeSnapshot: [
            'type' => $grantType === 'purchase' ? 'resources' : 'all',
            'plan' => [
                'code' => $planCode,
                'name' => $planCode === 'vip-pro' ? 'VIP Pro' : 'Single Resource',
            ],
            'resource_ids' => $grantType === 'purchase' ? [88] : [],
        ],
        quotaSnapshot: $quota,
        rulesVersion: 'rules-v3',
        startsAt: $startsAt,
        expiresAt: $expiresAt,
        priority: $priority,
        createdBy: 1,
        createdAt: $startsAt,
        updatedAt: $startsAt,
    );
}

$repository = new InMemoryEntitlementRepository();
$active = $repository->create(sr052_entitlement(
    userId: 101,
    grantType: 'membership',
    planCode: 'vip-pro',
    startsAt: '2026-01-01T00:00:00+00:00',
    expiresAt: '2026-12-31T23:59:59+00:00',
    quota: [
        'period_type' => 'month',
        'period_key' => '2026-06',
        'limit' => 20,
        'used' => 7,
        'reserved' => 2,
        'remaining' => 11,
        'reset_at' => '2026-07-01T00:00:00+00:00',
    ],
));
$expired = $repository->create(sr052_entitlement(
    userId: 101,
    grantType: 'membership',
    planCode: 'vip-old',
    startsAt: '2025-01-01T00:00:00+00:00',
    expiresAt: '2025-12-31T23:59:59+00:00',
    quota: [
        'limit' => 5,
        'used' => 5,
        'remaining' => 0,
        'reset_at' => '2025-02-01T00:00:00+00:00',
    ],
));
$revoked = $repository->create(sr052_entitlement(
    userId: 101,
    grantType: 'purchase',
    planCode: 'single',
    startsAt: '2026-02-01T00:00:00+00:00',
    expiresAt: null,
    quota: [
        'limit' => 1,
        'used' => 1,
        'remaining' => 0,
        'reset_at' => null,
    ],
))->revoke('2026-03-01T00:00:00+00:00', 1, 'refund');
$repository->save($revoked);
$repository->create(sr052_entitlement(
    userId: 202,
    grantType: 'membership',
    planCode: 'vip-other',
    startsAt: '2026-01-01T00:00:00+00:00',
    expiresAt: '2027-01-01T00:00:00+00:00',
    quota: [
        'limit' => 99,
        'used' => 0,
        'remaining' => 99,
        'reset_at' => '2026-07-01T00:00:00+00:00',
    ],
));

$cache = new InMemoryMeEntitlementsCacheStore();
$controller = new MeEntitlementsController($repository, $cache, static fn (): string => 'rules-v3');
$response = $controller->show(currentUserId: 101, atUtc: '2026-06-30T00:00:00+00:00');

sr052_assert(($response['user_id'] ?? null) === 101, 'response must be scoped to current user');
sr052_assert(($response['cache']['key'] ?? '') === 'sr:me:entitlements:101:rules-v3', 'cache key must include user and rules version');
sr052_assert(($response['cache']['invalidates'][0] ?? '') === 'sr:me:entitlements:101:rules-v3', 'cache invalidation must target same current-user key');

$items = $response['entitlements'] ?? [];
sr052_assert(is_array($items) && count($items) === 3, 'only current user entitlements should be returned');

$ids = array_column($items, 'id');
sort($ids);
sr052_assert($ids === [$active->id, $expired->id, $revoked->id], 'other users must not leak into response');

$activeRow = $items[0];
sr052_assert(($activeRow['plan']['code'] ?? '') === 'vip-pro', 'active row exposes plan code');
sr052_assert(($activeRow['expires_at'] ?? '') === '2026-12-31T23:59:59+00:00', 'active row exposes expiry');
sr052_assert(($activeRow['scope']['type'] ?? '') === 'all', 'active row exposes scope');
sr052_assert(($activeRow['quota']['remaining'] ?? null) === 11, 'active row exposes remaining quota');
sr052_assert(($activeRow['quota']['reset_at'] ?? '') === '2026-07-01T00:00:00+00:00', 'active row exposes quota reset time');

$statuses = array_column($items, 'status');
sr052_assert($statuses === ['active', 'expired', 'revoked'], 'projection must cover active, expired, and revoked states');

$repository->create(sr052_entitlement(
    userId: 101,
    grantType: 'membership',
    planCode: 'vip-new',
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2027-06-01T00:00:00+00:00',
    quota: [
        'limit' => 40,
        'used' => 1,
        'remaining' => 39,
        'reset_at' => '2026-07-01T00:00:00+00:00',
    ],
));
$cached = $controller->show(currentUserId: 101, atUtc: '2026-06-30T00:00:00+00:00');
sr052_assert(count($cached['entitlements'] ?? []) === 3, 'cache should serve stable current-user projection before invalidation');
$invalidated = $controller->invalidateForUser(101);
sr052_assert($invalidated === ['sr:me:entitlements:101:rules-v3'], 'invalidation should delete current-user rules-version key');
$refreshed = $controller->show(currentUserId: 101, atUtc: '2026-06-30T00:00:00+00:00');
sr052_assert(count($refreshed['entitlements'] ?? []) === 4, 'cache invalidation should immediately refresh changed entitlements');

$GLOBALS['sr052_current_user_id'] = 101;
$registrar = new MeEntitlementsRouteRegistrar($controller);
$registrar->register();
$registered = $GLOBALS['sr052_registered_route'] ?? [];
sr052_assert(($registered['namespace'] ?? '') === 'stock-resource/v1', 'route namespace should be registered');
sr052_assert(($registered['route'] ?? '') === '/me/entitlements', 'route path should be registered');
sr052_assert(($registered['args']['methods'] ?? '') === 'GET', 'route should expose GET only');
sr052_assert(is_callable($registered['args']['permission_callback'] ?? null), 'route should expose permission callback');
sr052_assert(($registered['args']['permission_callback'])() === true, 'route permission should require logged-in current user');
sr052_assert(($registrar->handle()['user_id'] ?? null) === 101, 'route handler should bind to get_current_user_id');

$rendered = sr_theme_account_membership_render($response);
sr052_assert(str_contains($rendered, 'VIP Pro'), 'membership template should render plan name');
sr052_assert(str_contains($rendered, '2026-12-31T23:59:59+00:00'), 'membership template should render expiry');
sr052_assert(str_contains($rendered, 'all'), 'membership template should render scope');
sr052_assert(str_contains($rendered, '11'), 'membership template should render remaining quota');
sr052_assert(str_contains($rendered, '2026-07-01T00:00:00+00:00'), 'membership template should render reset time');

$empty = $controller->show(currentUserId: 303, atUtc: '2026-06-30T00:00:00+00:00');
sr052_assert(($empty['state'] ?? '') === 'empty', 'empty state should be explicit');
sr052_assert(($empty['entitlements'] ?? ['not-empty']) === [], 'empty state should not fabricate rows');

echo "SR-052 me entitlements API check passed\n";
