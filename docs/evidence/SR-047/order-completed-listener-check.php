<?php
declare(strict_types=1);

use StockResource\Contracts\Dto\OrderCompletedEvent;
use StockResource\Contracts\Value\PositiveId;
use StockResource\Contracts\Value\UtcDateTime;
use StockResource\Core\Commerce\OrderSnapshot\OrderItemBusinessSnapshot;
use StockResource\Core\Commerce\OrderSnapshot\OrderSnapshotService;
use StockResource\Core\Integration\Edd\EddOrderAdapter;
use StockResource\Entitlements\Infrastructure\Repository\Entitlement;
use StockResource\Entitlements\Infrastructure\Repository\EntitlementException;
use StockResource\Entitlements\Infrastructure\Repository\EntitlementRepository;
use StockResource\Entitlements\Infrastructure\Repository\InMemoryEntitlementRepository;
use StockResource\Entitlements\Integration\EddOrderListener;

$root = dirname(__DIR__, 3);

foreach ([
    '/packages/sr-contracts/src/Exception/ContractException.php',
    '/packages/sr-contracts/src/Exception/ValidationException.php',
    '/packages/sr-contracts/src/Value/PositiveId.php',
    '/packages/sr-contracts/src/Value/UtcDateTime.php',
    '/packages/sr-contracts/src/Value/Money.php',
    '/packages/sr-contracts/src/Dto/OrderCompletedEvent.php',
    '/packages/sr-core/src/Integration/Edd/EddAdapterException.php',
    '/packages/sr-core/src/Integration/Edd/EddCustomerSnapshot.php',
    '/packages/sr-core/src/Integration/Edd/EddOrderSnapshot.php',
    '/packages/sr-core/src/Integration/Edd/EddOrderItemSnapshot.php',
    '/packages/sr-core/src/Integration/Edd/EddOrderAdapter.php',
    '/packages/sr-core/src/Commerce/OrderSnapshot/OrderSnapshotException.php',
    '/packages/sr-core/src/Commerce/OrderSnapshot/OrderItemBusinessSnapshot.php',
    '/packages/sr-core/src/Commerce/OrderSnapshot/OrderSnapshotService.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementException.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementStatus.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/Entitlement.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementRepository.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/InMemoryEntitlementRepository.php',
    '/packages/sr-entitlements/src/Integration/EddOrderListener.php',
] as $sourceFile) {
    require_once $root.$sourceFile;
}

function sr047_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr047_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

final class Sr047FakeRuntime
{
    public string $hook = '';
    public mixed $callback = null;
    public int $priority = 0;
    public int $acceptedArgs = 0;

    public function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        $this->hook = $hook;
        $this->callback = $callback;
        $this->priority = $priority;
        $this->acceptedArgs = $acceptedArgs;
    }
}

final class Sr047RacingEntitlementRepository implements EntitlementRepository
{
    private InMemoryEntitlementRepository $inner;
    private bool $raceInjected = false;

    public function __construct(private readonly int $raceSourceOrderItemId)
    {
        $this->inner = new InMemoryEntitlementRepository();
    }

    public function create(Entitlement $entitlement): Entitlement
    {
        if ($entitlement->sourceOrderItemId === $this->raceSourceOrderItemId && ! $this->raceInjected) {
            $this->raceInjected = true;
            $this->inner->create($entitlement);

            throw EntitlementException::duplicateSourceOrderItem($this->raceSourceOrderItemId);
        }

        return $this->inner->create($entitlement);
    }

    public function save(Entitlement $entitlement): Entitlement
    {
        return $this->inner->save($entitlement);
    }

    public function find(int $id): ?Entitlement
    {
        return $this->inner->find($id);
    }

    public function forUser(int $userId): array
    {
        return $this->inner->forUser($userId);
    }

    public function findBySourceOrderItem(int $sourceOrderItemId): ?Entitlement
    {
        return $this->inner->findBySourceOrderItem($sourceOrderItemId);
    }

    public function currentForUserResource(int $userId, int $resourceId, string $atUtc): ?Entitlement
    {
        return $this->inner->currentForUserResource($userId, $resourceId, $atUtc);
    }
}

/**
 * @param array<string, mixed> $termsSnapshot
 */
function sr047_snapshot(
    int $orderItemId,
    string $productType,
    ?int $resourceId,
    ?int $versionId,
    ?int $planDownloadId,
    ?string $planCode,
    array $termsSnapshot,
): OrderItemBusinessSnapshot {
    return new OrderItemBusinessSnapshot(
        orderId: 5001,
        orderItemId: $orderItemId,
        customerId: 901,
        userId: 1001,
        downloadId: $planDownloadId ?? $resourceId ?? 8001,
        priceId: 1,
        quantity: 1,
        currency: 'USD',
        unitAmount: '19.00',
        subtotalAmount: '19.00',
        discountAmount: '0.00',
        taxAmount: '0.00',
        totalAmount: '19.00',
        productType: $productType,
        rulesVersion: 'rules-2026-06',
        accessMode: $productType === 'resource' ? 'purchase' : 'vip',
        refundStatus: 'none',
        resourceId: $resourceId,
        versionId: $versionId,
        planDownloadId: $planDownloadId,
        planCode: $planCode,
        termsSnapshot: $termsSnapshot,
        capturedAt: '2026-06-29T10:00:00+00:00',
        idempotencyKey: 'snapshot-'.$orderItemId,
    );
}

function sr047_event(int ...$orderItemIds): OrderCompletedEvent
{
    return new OrderCompletedEvent(
        orderId: PositiveId::fromInt(5001),
        customerId: PositiveId::fromInt(901),
        completedAt: UtcDateTime::fromString('2026-06-29T10:00:00Z'),
        orderItemIds: array_map(static fn (int $id): PositiveId => PositiveId::fromInt($id), $orderItemIds),
    );
}

$repository = new InMemoryEntitlementRepository();
$listener = new EddOrderListener($repository);

$resourceSnapshot = sr047_snapshot(
    orderItemId: 7001,
    productType: 'resource',
    resourceId: 3001,
    versionId: 4001,
    planDownloadId: null,
    planCode: null,
    termsSnapshot: ['access_mode' => 'purchase', 'rules_version' => 'rules-2026-06'],
);
$membershipSnapshot = sr047_snapshot(
    orderItemId: 7002,
    productType: 'membership_plan',
    resourceId: null,
    versionId: null,
    planDownloadId: 9001,
    planCode: 'vip-monthly',
    termsSnapshot: [
        'duration_value' => 1,
        'duration_unit' => 'month',
        'scope_type' => 'taxonomies',
        'scope_rules_json' => json_encode(['taxonomy_term_ids' => [11, 12]], JSON_THROW_ON_ERROR),
        'excluded_resource_ids' => json_encode([3999], JSON_THROW_ON_ERROR),
        'quota_period' => 'month',
        'quota_limit' => 30,
        'redownload_policy' => 'same_resource_once_per_period',
        'priority' => 120,
        'rules_version' => 'rules-2026-06',
    ],
);

$first = $listener->handle(sr047_event(7001, 7002), [$resourceSnapshot, $membershipSnapshot]);
sr047_same(2, count($first['created']), 'resource and membership items are granted on first completion');
sr047_same(0, count($first['reused']), 'first completion does not reuse existing grants');
sr047_same(0, count($first['failed']), 'valid order snapshots have no failures');

$resourceGrant = $repository->findBySourceOrderItem(7001);
sr047_assert($resourceGrant !== null, 'resource grant is persisted by source order item id');
sr047_same('resource', $resourceGrant->grantType, 'resource product creates resource entitlement');
sr047_same('order_item', $resourceGrant->sourceType, 'resource source type is order_item');
sr047_same(3001, $resourceGrant->resourceId, 'resource grant keeps resource id');
sr047_same('resources', $resourceGrant->scopeType, 'resource grant scope is resources');
sr047_same([3001], $resourceGrant->scopeSnapshot['resource_ids'] ?? null, 'resource grant scopes to purchased resource');
sr047_same(4001, $resourceGrant->scopeSnapshot['version_id'] ?? null, 'resource grant keeps purchased version');
sr047_same(null, $resourceGrant->expiresAt, 'resource purchase does not expire by default');

$membershipGrant = $repository->findBySourceOrderItem(7002);
sr047_assert($membershipGrant !== null, 'membership grant is persisted by source order item id');
sr047_same('membership', $membershipGrant->grantType, 'membership product creates membership entitlement');
sr047_same(9001, $membershipGrant->planDownloadId, 'membership grant keeps plan download id');
sr047_same(null, $membershipGrant->resourceId, 'membership grant is not bound to one resource');
sr047_same('taxonomies', $membershipGrant->scopeType, 'membership scope type comes from snapshot');
sr047_same([11, 12], $membershipGrant->scopeSnapshot['taxonomy_term_ids'] ?? null, 'membership taxonomy scope is flattened for AccessDecision');
sr047_same([3999], $membershipGrant->scopeSnapshot['excluded_resource_ids'] ?? null, 'membership exclusions are preserved');
sr047_same(30, $membershipGrant->quotaSnapshot['limit'] ?? null, 'membership quota limit is preserved');
sr047_same('month', $membershipGrant->quotaSnapshot['period'] ?? null, 'membership quota period is preserved');
sr047_same(120, $membershipGrant->priority, 'membership priority comes from terms snapshot');
sr047_same('2026-07-29T10:00:00+00:00', $membershipGrant->expiresAt, 'membership duration creates expiry');

for ($i = 0; $i < 10; $i++) {
    $replay = $listener->handle(sr047_event(7001, 7002), [$resourceSnapshot, $membershipSnapshot]);
    sr047_same(0, count($replay['created']), 'replayed completion does not create duplicate grants');
    sr047_same(2, count($replay['reused']), 'replayed completion reuses existing grants');
    sr047_same(0, count($replay['failed']), 'replayed completion has no failures');
}
sr047_same(2, count($repository->forUser(1001)), 'repeating completion ten times grants once per order item');

$partialRepository = new InMemoryEntitlementRepository();
$partialListener = new EddOrderListener($partialRepository);
$validResource = sr047_snapshot(
    orderItemId: 7101,
    productType: 'resource',
    resourceId: 3010,
    versionId: 4010,
    planDownloadId: null,
    planCode: null,
    termsSnapshot: ['access_mode' => 'purchase'],
);
$invalidMembership = sr047_snapshot(
    orderItemId: 7102,
    productType: 'membership_plan',
    resourceId: null,
    versionId: null,
    planDownloadId: null,
    planCode: null,
    termsSnapshot: ['duration' => ['value' => 1, 'unit' => 'month']],
);

$partial = $partialListener->handle(sr047_event(7101, 7102), [$validResource, $invalidMembership]);
sr047_same(1, count($partial['created']), 'partial failure still grants valid order item');
sr047_same(1, count($partial['failed']), 'invalid order item is reported as failed');
sr047_same(7102, $partial['failed'][0]['order_item_id'] ?? null, 'failed item id is stable');
sr047_same(1, count($partialRepository->forUser(1001)), 'partial failure does not roll back valid grant');

$fixedMembership = sr047_snapshot(
    orderItemId: 7102,
    productType: 'membership_plan',
    resourceId: null,
    versionId: null,
    planDownloadId: 9010,
    planCode: 'vip-retry',
    termsSnapshot: [
        'duration' => ['value' => 7, 'unit' => 'day'],
        'scope' => ['type' => 'all', 'rules' => [], 'excluded_resource_ids' => []],
        'quota' => ['period' => 'week', 'limit' => 7, 'redownload_policy' => 'count_each'],
    ],
);
$retry = $partialListener->handle(sr047_event(7101, 7102), [$validResource, $fixedMembership]);
sr047_same(1, count($retry['created']), 'retry creates only the previously failed order item');
sr047_same(1, count($retry['reused']), 'retry reuses the already granted valid item');
sr047_same(0, count($retry['failed']), 'retry succeeds after invalid snapshot is fixed');
sr047_same(2, count($partialRepository->forUser(1001)), 'retry leaves exactly one entitlement per order item');

$fixture = [
    'customers' => [
        901 => ['id' => 901, 'user_id' => 1001, 'email' => 'buyer@example.test', 'name' => 'Buyer'],
    ],
    'orders' => [
        5001 => [
            'id' => 5001,
            'type' => 'sale',
            'status' => 'complete',
            'customer_id' => 901,
            'subtotal' => '29.000000000',
            'tax' => '0.000000000',
            'total' => '29.000000000',
            'currency' => 'USD',
            'date_created' => '2026-06-29T09:58:00Z',
            'date_completed' => '2026-06-29T10:00:00Z',
        ],
    ],
    'items' => [
        5001 => [[
            'id' => 7201,
            'order_id' => 5001,
            'product_id' => 9001,
            'price_id' => 1,
            'quantity' => 1,
            'subtotal' => '29.000000000',
            'tax' => '0.000000000',
            'total' => '29.000000000',
            'snapshot' => [
                'product_type' => 'membership_plan',
                'plan_download_id' => 9001,
                'plan_code' => 'vip-monthly',
                'access_mode' => 'vip',
                'rules_version' => 'rules-2026-06',
                'price_id' => 1,
                'unit_amount' => '29.00',
                'discount_amount' => '0',
                'total_amount' => '29.00',
                'terms_snapshot' => [
                    'duration_value' => 1,
                    'duration_unit' => 'month',
                    'scope_type' => 'resources',
                    'scope_rules_json' => json_encode(['resource_ids' => [3001, 3002]], JSON_THROW_ON_ERROR),
                    'excluded_resource_ids' => json_encode([3999], JSON_THROW_ON_ERROR),
                    'quota_period' => 'month',
                    'quota_limit' => 30,
                    'redownload_policy' => 'same_resource_once_per_period',
                    'priority' => 130,
                ],
            ],
        ]],
    ],
];
$adapter = new EddOrderAdapter($fixture);
$snapshotService = new OrderSnapshotService();
$runtimeRepository = new InMemoryEntitlementRepository();
$runtimeListener = new EddOrderListener($runtimeRepository);
$runtime = new Sr047FakeRuntime();
$runtimeListener->registerHooks($runtime, static fn (int $orderId): array => [
    'event' => $adapter->completedEvent($orderId, '2026-06-29T10:00:00Z'),
    'snapshots' => $snapshotService->snapshotsForOrder($adapter, $orderId, 1001),
]);
sr047_same('edd_complete_purchase', $runtime->hook, 'listener registers mission-critical EDD completed purchase hook');
sr047_same(1, $runtime->acceptedArgs, 'completed purchase hook consumes order id');
sr047_assert(is_callable($runtime->callback), 'completed purchase callback is registered');
$runtimeResult = ($runtime->callback)(5001);
sr047_same(1, count($runtimeResult['created']), 'registered hook creates entitlement from real order snapshots');
$runtimeGrant = $runtimeRepository->findBySourceOrderItem(7201);
sr047_assert($runtimeGrant !== null, 'runtime hook grant is persisted');
sr047_same('membership', $runtimeGrant->grantType, 'real normalized membership snapshot creates membership grant');
sr047_same([3001, 3002], $runtimeGrant->scopeSnapshot['resource_ids'] ?? null, 'real normalized scope JSON maps to resource ids');
sr047_same(30, $runtimeGrant->quotaSnapshot['limit'] ?? null, 'real normalized quota scalar maps to quota snapshot');

$raceRepository = new Sr047RacingEntitlementRepository(7001);
$raceListener = new EddOrderListener($raceRepository);
$race = $raceListener->handle(sr047_event(7001), [$resourceSnapshot]);
sr047_same(0, count($race['created']), 'concurrent duplicate branch does not report a second created entitlement');
sr047_same(1, count($race['reused']), 'concurrent duplicate branch re-reads the winning entitlement');
sr047_same(0, count($race['failed']), 'concurrent duplicate branch is replay safe');
sr047_same(1, count($raceRepository->forUser(1001)), 'concurrent duplicate branch stores exactly one entitlement');

$lifetime = sr047_snapshot(
    orderItemId: 7301,
    productType: 'membership_plan',
    resourceId: null,
    versionId: null,
    planDownloadId: 9030,
    planCode: 'bad-lifetime',
    termsSnapshot: [
        'duration_value' => 1,
        'duration_unit' => 'lifetime',
        'scope_type' => 'all',
        'quota_period' => 'month',
        'quota_limit' => 1,
        'redownload_policy' => 'count_each',
    ],
);
$lifetimeResult = (new EddOrderListener(new InMemoryEntitlementRepository()))->handle(sr047_event(7301), [$lifetime]);
sr047_same(0, count($lifetimeResult['created']), 'lifetime membership is not granted');
sr047_same(1, count($lifetimeResult['failed']), 'lifetime membership is rejected as invalid snapshot');

$unsupportedWeekDuration = sr047_snapshot(
    orderItemId: 7302,
    productType: 'membership_plan',
    resourceId: null,
    versionId: null,
    planDownloadId: 9031,
    planCode: 'bad-week-duration',
    termsSnapshot: [
        'duration_value' => 1,
        'duration_unit' => 'week',
        'scope_type' => 'all',
        'quota_period' => 'week',
        'quota_limit' => 1,
        'redownload_policy' => 'count_each',
    ],
);
$weekDurationResult = (new EddOrderListener(new InMemoryEntitlementRepository()))->handle(
    sr047_event(7302),
    [$unsupportedWeekDuration],
);
sr047_same(0, count($weekDurationResult['created']), 'week membership duration is not granted');
sr047_same(1, count($weekDurationResult['failed']), 'week duration follows SR-044 duration allowlist');

echo "SR-047 order completed listener checks passed.\n";
