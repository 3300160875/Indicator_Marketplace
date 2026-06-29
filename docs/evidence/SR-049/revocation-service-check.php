<?php
declare(strict_types=1);

use StockResource\Contracts\Dto\OrderRefundedEvent;
use StockResource\Contracts\Value\PositiveId;
use StockResource\Contracts\Value\UtcDateTime;
use StockResource\Entitlements\Application\RevocationService;
use StockResource\Entitlements\Infrastructure\Repository\Entitlement;
use StockResource\Entitlements\Infrastructure\Repository\EntitlementStatus;
use StockResource\Entitlements\Infrastructure\Repository\InMemoryEntitlementRepository;

$root = dirname(__DIR__, 3);

foreach ([
    '/packages/sr-contracts/src/Exception/ContractException.php',
    '/packages/sr-contracts/src/Exception/ValidationException.php',
    '/packages/sr-contracts/src/Value/PositiveId.php',
    '/packages/sr-contracts/src/Value/UtcDateTime.php',
    '/packages/sr-contracts/src/Dto/OrderRefundedEvent.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementException.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementStatus.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/Entitlement.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementRepository.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/InMemoryEntitlementRepository.php',
    '/packages/sr-entitlements/src/Application/RevocationService.php',
] as $sourceFile) {
    require_once $root.$sourceFile;
}

function sr049_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr049_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr049_entitlement(
    int $userId,
    int $orderId,
    int $orderItemId,
    ?int $resourceId,
    string $grantType,
    string $createdAt = '2026-06-29T09:00:00+00:00',
): Entitlement {
    return Entitlement::fromSnapshot(
        userId: $userId,
        eddCustomerId: 900 + $userId,
        grantType: $grantType,
        sourceType: 'order_item',
        sourceOrderId: $orderId,
        sourceOrderItemId: $orderItemId,
        planDownloadId: $grantType === 'membership' ? 7001 : null,
        parentEntitlementId: null,
        resourceId: $resourceId,
        scopeType: $resourceId === null ? 'all' : 'resources',
        scopeSnapshot: $resourceId === null
            ? ['type' => 'all', 'plan_code' => 'vip-monthly']
            : ['type' => 'resources', 'resource_ids' => [$resourceId], 'version_id' => 4001],
        quotaSnapshot: $grantType === 'membership' ? ['limit' => 30, 'remaining' => 30] : null,
        rulesVersion: 'rules-2026-06',
        startsAt: $createdAt,
        expiresAt: $grantType === 'membership' ? '2026-07-29T09:00:00+00:00' : null,
        priority: $grantType === 'membership' ? 100 : 10,
        createdBy: $userId,
        createdAt: $createdAt,
        updatedAt: $createdAt,
    );
}

$audit = [];
$invalidated = [];
$serviceFactory = static function (InMemoryEntitlementRepository $repository) use (&$audit, &$invalidated): RevocationService {
    return new RevocationService(
        repository: $repository,
        auditSink: static function (array $event) use (&$audit): void {
            $audit[] = $event;
        },
        cacheInvalidator: static function (array $keys) use (&$invalidated): void {
            $invalidated = array_values(array_unique(array_merge($invalidated, $keys)));
        },
    );
};

$repository = new InMemoryEntitlementRepository();
$resourceGrant = $repository->create(sr049_entitlement(1001, 8001, 9001, 3001, 'resource'));
$membershipGrant = $repository->create(sr049_entitlement(1001, 8001, 9002, null, 'membership'));
$historicalSignature = $resourceGrant->snapshotSignature();
$service = $serviceFactory($repository);

$refund = new OrderRefundedEvent(
    orderId: PositiveId::fromInt(8001),
    refundedAt: UtcDateTime::fromString('2026-06-29T10:00:00Z'),
    refundedOrderItemIds: [PositiveId::fromInt(9001)],
    fullRefund: false,
);
$refundResult = $service->handleRefundedOrder($refund);

$revokedResource = $repository->find($resourceGrant->id);
$stillActiveMembership = $repository->find($membershipGrant->id);
sr049_same([$resourceGrant->id], array_map(static fn (Entitlement $item): int => $item->id, $refundResult['revoked']), 'refund revokes matching order item entitlement');
sr049_same(EntitlementStatus::Revoked, $revokedResource->status, 'refunded entitlement is revoked');
sr049_same('refund:order:8001:item:9001', $revokedResource->revokeReason, 'refund revoke reason is stable');
sr049_same($historicalSignature, $revokedResource->snapshotSignature(), 'refund revoke does not rewrite entitlement snapshot');
sr049_same(9001, $revokedResource->sourceOrderItemId, 'refund revoke does not rewrite historical source order item');
sr049_assert(! $revokedResource->isActive('2026-06-29T10:00:01+00:00'), 'revoked entitlement blocks new access/token decisions immediately');
sr049_same(EntitlementStatus::Active, $stillActiveMembership->status, 'partial refund does not revoke unrelated order items');
sr049_assert(in_array('user:1001:entitlements', $invalidated, true), 'refund invalidates user entitlement cache');
sr049_assert(in_array('user:1001:download_tokens', $invalidated, true), 'refund invalidates pending download token decisions');
sr049_same('refund_revoke', $audit[0]['action'] ?? null, 'refund revoke writes audit event');

$replay = $service->handleRefundedOrder($refund);
sr049_same(0, count($replay['revoked']), 'refund replay does not revoke twice');
sr049_same([$resourceGrant->id], array_map(static fn (Entitlement $item): int => $item->id, $replay['already_revoked']), 'refund replay reports already revoked entitlement');

$retryRepo = new InMemoryEntitlementRepository();
$retryGrant = $retryRepo->create(sr049_entitlement(1004, 8101, 9101, 3301, 'resource'));
$cacheFailure = new RuntimeException('cache down');
$failingService = new RevocationService(
    repository: $retryRepo,
    auditSink: static function (array $event): void {
    },
    cacheInvalidator: static function (array $keys) use ($cacheFailure): void {
        throw $cacheFailure;
    },
);
$retryRefund = new OrderRefundedEvent(
    orderId: PositiveId::fromInt(8101),
    refundedAt: UtcDateTime::fromString('2026-06-29T10:30:00Z'),
    refundedOrderItemIds: [PositiveId::fromInt(9101)],
    fullRefund: false,
);
try {
    $failingService->handleRefundedOrder($retryRefund);
    throw new RuntimeException('cache failure should bubble for retry');
} catch (RuntimeException $exception) {
    sr049_same('cache down', $exception->getMessage(), 'cache invalidation failure is not swallowed');
}
sr049_same(EntitlementStatus::Revoked, $retryRepo->find($retryGrant->id)->status, 'failed invalidation attempt still persists revoke state');

$retryInvalidated = [];
$retryService = new RevocationService(
    repository: $retryRepo,
    auditSink: static function (array $event): void {
    },
    cacheInvalidator: static function (array $keys) use (&$retryInvalidated): void {
        $retryInvalidated = array_values(array_unique(array_merge($retryInvalidated, $keys)));
    },
);
$retryResult = $retryService->handleRefundedOrder($retryRefund);
sr049_same([$retryGrant->id], array_map(static fn (Entitlement $item): int => $item->id, $retryResult['already_revoked']), 'refund replay sees already revoked entitlement after cache failure');
sr049_assert(in_array('user:1004:download_tokens', $retryInvalidated, true), 'refund replay re-emits token invalidation after cache failure');

$manualRepo = new InMemoryEntitlementRepository();
$manualAudit = [];
$manualInvalidated = [];
$manualService = new RevocationService(
    repository: $manualRepo,
    auditSink: static function (array $event) use (&$manualAudit): void {
        $manualAudit[] = $event;
    },
    cacheInvalidator: static function (array $keys) use (&$manualInvalidated): void {
        $manualInvalidated = array_values(array_unique(array_merge($manualInvalidated, $keys)));
    },
);

$manualGrant = $manualService->grantManual([
    'user_id' => 1002,
    'resource_id' => 3100,
    'scope_type' => 'resources',
    'scope_snapshot' => ['type' => 'resources', 'resource_ids' => [3100]],
    'quota_snapshot' => null,
    'rules_version' => 'manual-rules-2026-06',
    'starts_at' => '2026-06-29T11:00:00+00:00',
    'expires_at' => null,
    'priority' => 50,
    'actor_id' => 77,
    'reason' => 'customer support adjustment',
]);
sr049_same('manual', $manualGrant->grantType, 'manual grant uses manual grant type');
sr049_same(77, $manualGrant->createdBy, 'manual grant records actor');
sr049_same('manual_grant', $manualAudit[0]['action'] ?? null, 'manual grant writes audit event');
sr049_assert(in_array('user:1002:entitlements', $manualInvalidated, true), 'manual grant invalidates user entitlement cache');

$manualRevoke = $manualService->revokeManual(
    entitlementId: $manualGrant->id,
    actorId: 77,
    reason: 'support correction',
    revokedAt: '2026-06-29T11:05:00+00:00',
);
sr049_same(EntitlementStatus::Revoked, $manualRevoke->status, 'manual revoke updates status');
sr049_same('manual:support correction', $manualRevoke->revokeReason, 'manual revoke records reason');
sr049_same('manual_revoke', $manualAudit[1]['action'] ?? null, 'manual revoke writes audit event');

try {
    $manualService->grantManual([
        'user_id' => 1003,
        'resource_id' => 3200,
        'scope_type' => 'resources',
        'scope_snapshot' => ['type' => 'resources', 'resource_ids' => [3200]],
        'rules_version' => 'manual-rules-2026-06',
        'starts_at' => '2026-06-29T12:00:00+00:00',
        'actor_id' => 77,
        'reason' => ' ',
    ]);
    throw new RuntimeException('manual grant without reason should fail');
} catch (InvalidArgumentException $exception) {
    sr049_same('reason is required.', $exception->getMessage(), 'manual grant requires reason');
}

try {
    $manualService->grantManual([
        'user_id' => 1003,
        'resource_id' => 0,
        'scope_type' => 'resources',
        'scope_snapshot' => ['type' => 'resources', 'resource_ids' => [3200]],
        'rules_version' => 'manual-rules-2026-06',
        'starts_at' => '2026-06-29T12:00:00+00:00',
        'actor_id' => 77,
        'reason' => 'invalid resource id',
    ]);
    throw new RuntimeException('manual grant with non-positive resource id should fail');
} catch (InvalidArgumentException $exception) {
    sr049_same('resource_id must be positive when provided.', $exception->getMessage(), 'manual grant validates resource id');
}

try {
    $manualService->grantManual([
        'user_id' => 1003,
        'plan_download_id' => 0,
        'scope_type' => 'all',
        'scope_snapshot' => ['type' => 'all'],
        'rules_version' => 'manual-rules-2026-06',
        'starts_at' => '2026-06-29T12:00:00+00:00',
        'actor_id' => 77,
        'reason' => 'invalid plan id',
    ]);
    throw new RuntimeException('manual grant with non-positive plan download id should fail');
} catch (InvalidArgumentException $exception) {
    sr049_same('plan_download_id must be positive when provided.', $exception->getMessage(), 'manual grant validates plan download id');
}

try {
    $manualService->grantManual([
        'user_id' => 1003,
        'resource_id' => 3200,
        'scope_type' => 'resources',
        'scope_snapshot' => ['type' => 'resources', 'resource_ids' => [3200]],
        'rules_version' => 'manual-rules-2026-06',
        'starts_at' => '2026-06-29T12:00:00+00:00',
        'expires_at' => '2026-06-29T11:59:59+00:00',
        'actor_id' => 77,
        'reason' => 'invalid expiry',
    ]);
    throw new RuntimeException('manual grant with expires_at before starts_at should fail');
} catch (InvalidArgumentException $exception) {
    sr049_same('expires_at must be later than starts_at.', $exception->getMessage(), 'manual grant validates expiry ordering');
}

try {
    $manualService->revokeManual($manualGrant->id, 77, '', '2026-06-29T11:10:00+00:00');
    throw new RuntimeException('manual revoke without reason should fail');
} catch (InvalidArgumentException $exception) {
    sr049_same('reason is required.', $exception->getMessage(), 'manual revoke requires reason');
}

echo "SR-049 revocation service checks passed.\n";
