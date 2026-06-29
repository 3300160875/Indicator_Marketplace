<?php
declare(strict_types=1);

use StockResource\Contracts\Entitlement\AccessDecision;
use StockResource\Contracts\Entitlement\AccessDecisionContext;
use StockResource\Entitlements\Application\EntitlementService;
use StockResource\Entitlements\Infrastructure\Repository\Entitlement;
use StockResource\Entitlements\Infrastructure\Repository\InMemoryEntitlementRepository;

$root = dirname(__DIR__, 3);

foreach ([
    '/packages/sr-contracts/src/Entitlement/AccessDecision.php',
    '/packages/sr-contracts/src/Entitlement/AccessDecisionContext.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementException.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementStatus.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/Entitlement.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementRepository.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/InMemoryEntitlementRepository.php',
    '/packages/sr-entitlements/src/Application/EntitlementService.php',
] as $sourceFile) {
    require_once $root.$sourceFile;
}

function sr046_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr046_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr046_decide(
    InMemoryEntitlementRepository $repository,
    AccessDecisionContext $context,
    ?callable $quotaResolver = null,
): AccessDecision {
    return (new EntitlementService($repository, $quotaResolver))->decide($context);
}

function sr046_context(
    string $accessMode,
    ?int $userId = 100,
    int $resourceId = 700,
    string $resourceStatus = 'published',
    array $taxonomyTermIds = [8],
    string $atUtc = '2026-06-29T10:00:00+00:00',
): AccessDecisionContext {
    return new AccessDecisionContext(
        resourceId: $resourceId,
        userId: $userId,
        accessMode: $accessMode,
        resourceStatus: $resourceStatus,
        taxonomyTermIds: $taxonomyTermIds,
        atUtc: $atUtc,
    );
}

function sr046_entitlement(
    int $userId,
    string $grantType,
    ?int $resourceId,
    string $scopeType,
    array $scopeSnapshot,
    ?array $quotaSnapshot,
    int $priority,
    string $startsAt,
    ?string $expiresAt,
    ?int $sourceOrderItemId,
): Entitlement {
    return Entitlement::fromSnapshot(
        userId: $userId,
        eddCustomerId: 900 + $userId,
        grantType: $grantType,
        sourceType: $grantType === 'manual' ? 'manual_grant' : 'order_item',
        sourceOrderId: $sourceOrderItemId === null ? null : 5000 + $sourceOrderItemId,
        sourceOrderItemId: $sourceOrderItemId,
        planDownloadId: null,
        parentEntitlementId: null,
        resourceId: $resourceId,
        scopeType: $scopeType,
        scopeSnapshot: $scopeSnapshot,
        quotaSnapshot: $quotaSnapshot,
        rulesVersion: 'rules-v1',
        startsAt: $startsAt,
        expiresAt: $expiresAt,
        priority: $priority,
        createdBy: 1,
        createdAt: '2026-06-29T09:00:00+00:00',
        updatedAt: '2026-06-29T09:00:00+00:00',
    );
}

$empty = new InMemoryEntitlementRepository();

$unavailable = sr046_decide($empty, sr046_context('free', resourceStatus: 'archived'));
sr046_same(false, $unavailable->allowed, 'resource status is evaluated before free access');
sr046_same('resource_unavailable', $unavailable->reasonCode, 'unavailable resource reason code');
sr046_same('NONE', $unavailable->source, 'unavailable resource has no access source');

$free = sr046_decide($empty, sr046_context('free', userId: null));
sr046_same(true, $free->allowed, 'free resource allows anonymous access');
sr046_same('free_resource', $free->reasonCode, 'free reason code');
sr046_same('FREE', $free->source, 'free source');

$loginRequired = sr046_decide($empty, sr046_context('purchase', userId: null));
sr046_same(false, $loginRequired->allowed, 'paid resource requires login before entitlement checks');
sr046_same('login_required', $loginRequired->reasonCode, 'login required reason code');

$repo = new InMemoryEntitlementRepository();
$repo->create(sr046_entitlement(
    userId: 100,
    grantType: 'membership',
    resourceId: null,
    scopeType: 'all',
    scopeSnapshot: ['type' => 'all'],
    quotaSnapshot: ['remaining' => 10, 'limit' => 10],
    priority: 200,
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-12-31T00:00:00+00:00',
    sourceOrderItemId: 1001,
));
$purchase = $repo->create(sr046_entitlement(
    userId: 100,
    grantType: 'purchase',
    resourceId: 700,
    scopeType: 'resources',
    scopeSnapshot: ['type' => 'resources', 'resource_ids' => [700]],
    quotaSnapshot: ['remaining' => 1, 'limit' => 1],
    priority: 10,
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-08-01T00:00:00+00:00',
    sourceOrderItemId: 1002,
));

$purchaseDecision = sr046_decide($repo, sr046_context('purchase_or_vip'));
sr046_same(true, $purchaseDecision->allowed, 'single purchase wins before vip');
sr046_same('single_purchase', $purchaseDecision->reasonCode, 'single purchase reason');
sr046_same('PURCHASE', $purchaseDecision->source, 'single purchase source');
sr046_same($purchase->id, $purchaseDecision->entitlementId, 'single purchase entitlement id');
sr046_same('2026-08-01T00:00:00+00:00', $purchaseDecision->expiresAt, 'single purchase expiry');
sr046_same(1, $purchaseDecision->quota['remaining'] ?? null, 'quota snapshot is exposed');

$manualRepo = new InMemoryEntitlementRepository();
$manual = $manualRepo->create(sr046_entitlement(
    userId: 100,
    grantType: 'manual',
    resourceId: 700,
    scopeType: 'resources',
    scopeSnapshot: ['type' => 'resources', 'resource_ids' => [700]],
    quotaSnapshot: null,
    priority: 300,
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: null,
    sourceOrderItemId: null,
));
$manualDecision = sr046_decide($manualRepo, sr046_context('vip'));
sr046_same(true, $manualDecision->allowed, 'manual grant wins before vip');
sr046_same('manual_grant', $manualDecision->reasonCode, 'manual reason');
sr046_same('MANUAL', $manualDecision->source, 'manual source');
sr046_same($manual->id, $manualDecision->entitlementId, 'manual entitlement id');

$vipRepo = new InMemoryEntitlementRepository();
$vipOlder = $vipRepo->create(sr046_entitlement(
    userId: 100,
    grantType: 'membership',
    resourceId: null,
    scopeType: 'all',
    scopeSnapshot: ['type' => 'all'],
    quotaSnapshot: ['remaining' => 4, 'limit' => 10],
    priority: 50,
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-07-01T00:00:00+00:00',
    sourceOrderItemId: 1003,
));
$vipNewer = $vipRepo->create(sr046_entitlement(
    userId: 100,
    grantType: 'membership',
    resourceId: null,
    scopeType: 'all',
    scopeSnapshot: ['type' => 'all'],
    quotaSnapshot: ['remaining' => 7, 'limit' => 10],
    priority: 50,
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-09-01T00:00:00+00:00',
    sourceOrderItemId: 1004,
));
$vipDecision = sr046_decide($vipRepo, sr046_context('vip'));
sr046_same(true, $vipDecision->allowed, 'vip entitlement allows vip resource');
sr046_same('vip_entitlement', $vipDecision->reasonCode, 'vip reason');
sr046_same('VIP', $vipDecision->source, 'vip source');
sr046_same($vipNewer->id, $vipDecision->entitlementId, 'stable sort picks later expiry when priority ties');

$expired = sr046_decide($vipRepo, sr046_context('vip', atUtc: '2026-09-01T00:00:00+00:00'));
sr046_same(false, $expired->allowed, 'expiry boundary is exclusive');
sr046_same('no_entitlement', $expired->reasonCode, 'expired entitlement denies access');

$excludedRepo = new InMemoryEntitlementRepository();
$excludedRepo->create(sr046_entitlement(
    userId: 100,
    grantType: 'membership',
    resourceId: null,
    scopeType: 'all',
    scopeSnapshot: ['type' => 'all', 'excluded_resource_ids' => [700]],
    quotaSnapshot: ['remaining' => 10],
    priority: 100,
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-12-31T00:00:00+00:00',
    sourceOrderItemId: 1005,
));
$excluded = sr046_decide($excludedRepo, sr046_context('vip'));
sr046_same(false, $excluded->allowed, 'excluded resource denies scoped vip entitlement');
sr046_same('scope_excluded', $excluded->reasonCode, 'scope exclusion reason');

$quotaRepo = new InMemoryEntitlementRepository();
$quotaRepo->create(sr046_entitlement(
    userId: 100,
    grantType: 'membership',
    resourceId: null,
    scopeType: 'resources',
    scopeSnapshot: ['type' => 'resources', 'resource_ids' => [700]],
    quotaSnapshot: ['remaining' => 0, 'limit' => 10, 'reset_at' => '2026-07-01T00:00:00+00:00'],
    priority: 100,
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-12-31T00:00:00+00:00',
    sourceOrderItemId: 1006,
));
$quota = sr046_decide($quotaRepo, sr046_context('vip'));
sr046_same(false, $quota->allowed, 'quota is evaluated after scope match');
sr046_same('quota_exhausted', $quota->reasonCode, 'quota exhausted reason');
sr046_same('2026-07-01T00:00:00+00:00', $quota->quota['reset_at'] ?? null, 'quota reset is exposed');

$taxonomyRepo = new InMemoryEntitlementRepository();
$taxonomyRepo->create(sr046_entitlement(
    userId: 100,
    grantType: 'membership',
    resourceId: null,
    scopeType: 'taxonomies',
    scopeSnapshot: ['type' => 'taxonomies', 'taxonomy_term_ids' => [9]],
    quotaSnapshot: ['remaining' => 3],
    priority: 100,
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-12-31T00:00:00+00:00',
    sourceOrderItemId: 1007,
));
$taxonomyDenied = sr046_decide($taxonomyRepo, sr046_context('vip', taxonomyTermIds: [8]));
sr046_same(false, $taxonomyDenied->allowed, 'taxonomy scoped entitlement denies non-matching resource');
sr046_same('scope_mismatch', $taxonomyDenied->reasonCode, 'taxonomy mismatch reason');
$taxonomyAllowed = sr046_decide($taxonomyRepo, sr046_context('vip', taxonomyTermIds: [9]));
sr046_same(true, $taxonomyAllowed->allowed, 'taxonomy scoped entitlement allows matching resource');
sr046_same('VIP', $taxonomyAllowed->source, 'taxonomy scoped vip source');

$arrayShape = $taxonomyAllowed->toArray();
foreach (['allowed', 'reason_code', 'source', 'entitlement_id', 'quota', 'expires_at', 'rules_version'] as $requiredKey) {
    sr046_assert(array_key_exists($requiredKey, $arrayShape), 'AccessDecision toArray includes '.$requiredKey);
}

echo "SR-046 access decision checks passed.\n";
