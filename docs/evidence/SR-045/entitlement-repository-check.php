<?php
declare(strict_types=1);

use StockResource\Entitlements\Infrastructure\Repository\Entitlement;
use StockResource\Entitlements\Infrastructure\Repository\EntitlementException;
use StockResource\Entitlements\Infrastructure\Repository\EntitlementRepository;
use StockResource\Entitlements\Infrastructure\Repository\InMemoryEntitlementRepository;

$root = dirname(__DIR__, 3);
require_once $root . '/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementException.php';
require_once $root . '/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementStatus.php';
require_once $root . '/packages/sr-entitlements/src/Infrastructure/Repository/Entitlement.php';
require_once $root . '/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementRepository.php';
require_once $root . '/packages/sr-entitlements/src/Infrastructure/Repository/InMemoryEntitlementRepository.php';

function sr045_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr045_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

function sr045_expect_error(string $expectedCode, callable $action): void
{
    try {
        $action();
    } catch (EntitlementException $exception) {
        sr045_same($expectedCode, $exception->codeName, 'unexpected exception code: expected ' . $expectedCode);
        return;
    }

    throw new RuntimeException('Expected exception not thrown: ' . $expectedCode);
}

$repository = new InMemoryEntitlementRepository();

$base = Entitlement::fromSnapshot(
    userId: 100,
    eddCustomerId: 900,
    grantType: 'purchase',
    sourceType: 'order_item',
    sourceOrderId: 500,
    sourceOrderItemId: 501,
    planDownloadId: null,
    parentEntitlementId: null,
    resourceId: 1001,
    scopeType: 'all',
    scopeSnapshot: ['type' => 'all'],
    quotaSnapshot: ['period' => 'count_each', 'limit' => 3],
    rulesVersion: 'rules-v1',
    startsAt: '2026-06-29T10:00:00+00:00',
    expiresAt: '2026-12-31T10:00:00+00:00',
    priority: 100,
    createdBy: 100,
    createdAt: '2026-06-29T10:00:00+00:00',
    updatedAt: '2026-06-29T10:00:00+00:00',
);

$created = $repository->create($base);
sr045_same(1, $created->id, 'create should allocate entitlement id from repository');
sr045_same(md5('{"type":"all"}|{"limit":3,"period":"count_each"}'), $created->snapshotSignature(), 'snapshot signature is stable');

$found = $repository->find(1);
sr045_same($created, $found, 'repository find returns persisted entitlement');

$foundBySourceItem = $repository->findBySourceOrderItem(501);
sr045_same($created, $foundBySourceItem, 'findBySourceOrderItem returns persisted entitlement');

sr045_same([$created], $repository->forUser(100), 'forUser returns user-scoped entitlements');

$current = $repository->currentForUserResource(100, 1001, '2026-06-29T12:00:00+00:00');
sr045_same($created, $current, 'currentForUserResource returns active entitlement at the given time');

$duplicate = Entitlement::fromSnapshot(
    userId: 100,
    eddCustomerId: 901,
    grantType: 'purchase',
    sourceType: 'order_item',
    sourceOrderId: 501,
    sourceOrderItemId: 501,
    planDownloadId: null,
    parentEntitlementId: null,
    resourceId: 1001,
    scopeType: 'all',
    scopeSnapshot: ['type' => 'all'],
    quotaSnapshot: ['period' => 'count_each', 'limit' => 3],
    rulesVersion: 'rules-v1',
    startsAt: '2026-06-29T10:00:00+00:00',
    expiresAt: '2026-12-31T10:00:00+00:00',
    priority: 100,
    createdBy: 100,
    createdAt: '2026-06-29T10:00:00+00:00',
    updatedAt: '2026-06-29T10:00:00+00:00',
);
sr045_expect_error('duplicate_source_order_item_id', static function () use ($repository, $duplicate): void {
    $repository->create($duplicate);
});

$revokeAt = '2026-06-29T13:00:00+00:00';
$revoked = $created->revoke($revokeAt, 888, 'test revoke');
$saved = $repository->save($revoked);
sr045_same(true, $saved->isActive('2026-06-29T14:00:00+00:00') === false, 'revoked entitlement is no longer active');

$updated = Entitlement::fromSnapshot(
    userId: 100,
    eddCustomerId: 900,
    grantType: 'purchase',
    sourceType: 'order_item',
    sourceOrderId: 500,
    sourceOrderItemId: 501,
    planDownloadId: null,
    parentEntitlementId: null,
    resourceId: 1001,
    scopeType: 'all',
    scopeSnapshot: ['type' => 'resources', 'resource_ids' => [1001]],
    quotaSnapshot: ['period' => 'count_each', 'limit' => 3],
    rulesVersion: 'rules-v1',
    startsAt: '2026-06-29T10:00:00+00:00',
    expiresAt: '2026-12-31T10:00:00+00:00',
    priority: 100,
    createdBy: 100,
    createdAt: '2026-06-29T10:00:00+00:00',
    updatedAt: '2026-06-29T10:00:00+00:00',
);
$updated = $updated->withId($created->id);
sr045_expect_error('snapshot_immutable_conflict', static function () use ($repository, $updated): void {
    $repository->save($updated);
});

sr045_same(null, $repository->currentForUserResource(100, 1001, '2026-06-29T14:00:00+00:00'), 'revoked entitlement is not current at later time');

echo "SR-045 entitlement repository checks passed.\n";
