<?php
declare(strict_types=1);

use StockResource\Entitlements\Application\MembershipService;
use StockResource\Entitlements\Infrastructure\Repository\Entitlement;
use StockResource\Entitlements\Infrastructure\Repository\InMemoryEntitlementRepository;

$root = dirname(__DIR__, 3);

foreach ([
    '/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementException.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementStatus.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/Entitlement.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementRepository.php',
    '/packages/sr-entitlements/src/Infrastructure/Repository/InMemoryEntitlementRepository.php',
    '/packages/sr-entitlements/src/Application/MembershipService.php',
] as $sourceFile) {
    require_once $root.$sourceFile;
}

function sr048_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr048_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr048_membership(
    int $userId,
    int $sourceOrderItemId,
    int $planDownloadId,
    string $planCode,
    string $startsAt,
    string $expiresAt,
    string $scopeType = 'all',
    array $scopeSnapshot = ['type' => 'all'],
    array $quotaSnapshot = ['limit' => 10, 'remaining' => 10],
    int $priority = 100,
): Entitlement {
    $scopeSnapshot['plan_code'] = $planCode;

    return Entitlement::fromSnapshot(
        userId: $userId,
        eddCustomerId: 900 + $userId,
        grantType: 'membership',
        sourceType: 'order_item',
        sourceOrderId: 5000 + $sourceOrderItemId,
        sourceOrderItemId: $sourceOrderItemId,
        planDownloadId: $planDownloadId,
        parentEntitlementId: null,
        resourceId: null,
        scopeType: $scopeType,
        scopeSnapshot: $scopeSnapshot,
        quotaSnapshot: $quotaSnapshot,
        rulesVersion: 'rules-2026-06',
        startsAt: $startsAt,
        expiresAt: $expiresAt,
        priority: $priority,
        createdBy: $userId,
        createdAt: $startsAt,
        updatedAt: $startsAt,
    );
}

function sr048_request(
    int $sourceOrderItemId,
    int $planDownloadId = 9001,
    string $planCode = 'vip-monthly',
    string $purchasedAt = '2026-06-15T10:00:00+00:00',
): array {
    return [
        'user_id' => 1001,
        'edd_customer_id' => 1901,
        'source_order_id' => 8000 + $sourceOrderItemId,
        'source_order_item_id' => $sourceOrderItemId,
        'plan_download_id' => $planDownloadId,
        'plan_code' => $planCode,
        'duration_value' => 1,
        'duration_unit' => 'month',
        'scope_type' => 'all',
        'scope_snapshot' => ['type' => 'all', 'plan_code' => $planCode],
        'quota_snapshot' => ['period' => 'month', 'limit' => 30, 'remaining' => 30],
        'rules_version' => 'rules-2026-06',
        'priority' => 100,
        'purchased_at' => $purchasedAt,
        'created_by' => 1001,
    ];
}

$activeRepo = new InMemoryEntitlementRepository();
$existingActive = $activeRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6001,
    planDownloadId: 9001,
    planCode: 'vip-monthly',
    startsAt: '2026-06-01T10:00:00+00:00',
    expiresAt: '2026-07-01T10:00:00+00:00',
));
$activeService = new MembershipService($activeRepo);
$renewed = $activeService->createRenewalSegment(sr048_request(6002));
sr048_same('2026-07-01T10:00:00+00:00', $renewed->startsAt, 'active same-plan renewal starts from latest existing expiry');
sr048_same('2026-08-01T10:00:00+00:00', $renewed->expiresAt, 'active same-plan renewal extends from latest existing expiry');
sr048_same(6002, $renewed->sourceOrderItemId, 'renewal creates a new source segment');
sr048_same('2026-07-01T10:00:00+00:00', $existingActive->expiresAt, 'historical segment object is not modified');
sr048_same('2026-07-01T10:00:00+00:00', $activeRepo->find($existingActive->id)->expiresAt, 'historical repository segment is not modified');

$expiredRepo = new InMemoryEntitlementRepository();
$expiredRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6101,
    planDownloadId: 9001,
    planCode: 'vip-monthly',
    startsAt: '2026-04-01T10:00:00+00:00',
    expiresAt: '2026-05-01T10:00:00+00:00',
));
$expiredService = new MembershipService($expiredRepo);
$afterGap = $expiredService->createRenewalSegment(sr048_request(6102, purchasedAt: '2026-06-15T10:00:00+00:00'));
sr048_same('2026-06-15T10:00:00+00:00', $afterGap->startsAt, 'expired same-plan renewal starts from purchase time');
sr048_same('2026-07-15T10:00:00+00:00', $afterGap->expiresAt, 'expired same-plan renewal duration starts at now');

$revokedRepo = new InMemoryEntitlementRepository();
$revokedExisting = $revokedRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6151,
    planDownloadId: 9001,
    planCode: 'vip-monthly',
    startsAt: '2026-06-01T10:00:00+00:00',
    expiresAt: '2026-07-01T10:00:00+00:00',
));
$revokedRepo->save($revokedExisting->revoke('2026-06-10T00:00:00+00:00', 1001, 'refund'));
$afterRevoke = (new MembershipService($revokedRepo))->createRenewalSegment(sr048_request(
    sourceOrderItemId: 6152,
    purchasedAt: '2026-06-15T10:00:00+00:00',
));
sr048_same('2026-06-15T10:00:00+00:00', $afterRevoke->startsAt, 'revoked same-plan renewal starts from purchase time');

$multiRepo = new InMemoryEntitlementRepository();
$allPlan = $multiRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6201,
    planDownloadId: 9201,
    planCode: 'all-basic',
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-12-01T00:00:00+00:00',
    scopeType: 'all',
    scopeSnapshot: ['type' => 'all'],
    quotaSnapshot: ['limit' => 100, 'remaining' => 100],
    priority: 100,
));
$taxonomyPlan = $multiRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6202,
    planDownloadId: 9202,
    planCode: 'taxonomy-pro',
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-09-01T00:00:00+00:00',
    scopeType: 'taxonomies',
    scopeSnapshot: ['type' => 'taxonomies', 'taxonomy_term_ids' => [8]],
    quotaSnapshot: ['limit' => 100, 'remaining' => 100],
    priority: 100,
));
$resourcePlan = $multiRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6203,
    planDownloadId: 9203,
    planCode: 'resource-best',
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-08-01T00:00:00+00:00',
    scopeType: 'resources',
    scopeSnapshot: ['type' => 'resources', 'resource_ids' => [700]],
    quotaSnapshot: ['limit' => 20, 'remaining' => 5],
    priority: 50,
));
$multiService = new MembershipService($multiRepo);
$best = $multiService->chooseBestForResource(
    userId: 1001,
    resourceId: 700,
    taxonomyTermIds: [8],
    atUtc: '2026-06-29T10:00:00+00:00',
);
sr048_assert($best['entitlement'] !== null, 'best membership is selected');
sr048_same($resourcePlan->id, $best['entitlement']->id, 'more specific resource coverage wins before quota and priority');
sr048_same(['coverage', 'quota', 'priority', 'expires_at', 'id'], $best['sort_order'], 'choice explains stable sort dimensions');
sr048_same('resources', $best['reason']['coverage_type'] ?? null, 'choice explains winning coverage');

$quotaRepo = new InMemoryEntitlementRepository();
$lowQuota = $quotaRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6301,
    planDownloadId: 9301,
    planCode: 'low-quota',
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-09-01T00:00:00+00:00',
    scopeType: 'resources',
    scopeSnapshot: ['type' => 'resources', 'resource_ids' => [800]],
    quotaSnapshot: ['limit' => 10, 'remaining' => 1],
    priority: 200,
));
$highQuota = $quotaRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6302,
    planDownloadId: 9302,
    planCode: 'high-quota',
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-08-01T00:00:00+00:00',
    scopeType: 'resources',
    scopeSnapshot: ['type' => 'resources', 'resource_ids' => [800]],
    quotaSnapshot: ['limit' => 10, 'remaining' => 7],
    priority: 10,
));
$quotaBest = (new MembershipService($quotaRepo))->chooseBestForResource(
    userId: 1001,
    resourceId: 800,
    taxonomyTermIds: [],
    atUtc: '2026-06-29T10:00:00+00:00',
);
sr048_same($highQuota->id, $quotaBest['entitlement']->id, 'same coverage chooses higher remaining quota before priority');

$priorityRepo = new InMemoryEntitlementRepository();
$lowerPriority = $priorityRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6401,
    planDownloadId: 9401,
    planCode: 'lower-priority',
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-09-01T00:00:00+00:00',
    scopeType: 'all',
    scopeSnapshot: ['type' => 'all'],
    quotaSnapshot: ['limit' => 10, 'remaining' => 5],
    priority: 50,
));
$higherPriority = $priorityRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6402,
    planDownloadId: 9402,
    planCode: 'higher-priority',
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-08-01T00:00:00+00:00',
    scopeType: 'all',
    scopeSnapshot: ['type' => 'all'],
    quotaSnapshot: ['limit' => 10, 'remaining' => 5],
    priority: 100,
));
$priorityBest = (new MembershipService($priorityRepo))->chooseBestForResource(
    userId: 1001,
    resourceId: 900,
    taxonomyTermIds: [],
    atUtc: '2026-06-29T10:00:00+00:00',
);
sr048_same($higherPriority->id, $priorityBest['entitlement']->id, 'same coverage and quota chooses higher priority before later expiry');

$quotaClosedRepo = new InMemoryEntitlementRepository();
$quotaClosedRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6501,
    planDownloadId: 9501,
    planCode: 'unknown-quota',
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-09-01T00:00:00+00:00',
    scopeType: 'resources',
    scopeSnapshot: ['type' => 'resources', 'resource_ids' => [1000]],
    quotaSnapshot: [],
    priority: 500,
));
$usableQuota = $quotaClosedRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6502,
    planDownloadId: 9502,
    planCode: 'known-quota',
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-08-01T00:00:00+00:00',
    scopeType: 'resources',
    scopeSnapshot: ['type' => 'resources', 'resource_ids' => [1000]],
    quotaSnapshot: ['limit' => 10, 'remaining' => 1],
    priority: 100,
));
$quotaClosedRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6503,
    planDownloadId: 9503,
    planCode: 'exhausted-quota',
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-10-01T00:00:00+00:00',
    scopeType: 'resources',
    scopeSnapshot: ['type' => 'resources', 'resource_ids' => [1000]],
    quotaSnapshot: ['limit' => 10, 'remaining' => 0],
    priority: 1000,
));
$quotaClosedRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6504,
    planDownloadId: 9504,
    planCode: 'unavailable-quota',
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-11-01T00:00:00+00:00',
    scopeType: 'resources',
    scopeSnapshot: ['type' => 'resources', 'resource_ids' => [1000]],
    quotaSnapshot: ['available' => false, 'limit' => 999, 'remaining' => 999],
    priority: 2000,
));
$quotaClosedBest = (new MembershipService($quotaClosedRepo))->chooseBestForResource(
    userId: 1001,
    resourceId: 1000,
    taxonomyTermIds: [],
    atUtc: '2026-06-29T10:00:00+00:00',
);
sr048_same($usableQuota->id, $quotaClosedBest['entitlement']->id, 'missing or exhausted quota does not outrank known usable quota');

$tieRepo = new InMemoryEntitlementRepository();
$laterExpiry = $tieRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6601,
    planDownloadId: 9601,
    planCode: 'later-expiry',
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-10-01T00:00:00+00:00',
    scopeType: 'all',
    scopeSnapshot: ['type' => 'all'],
    quotaSnapshot: ['limit' => 10, 'remaining' => 5],
    priority: 100,
));
$tieRepo->create(sr048_membership(
    userId: 1001,
    sourceOrderItemId: 6602,
    planDownloadId: 9602,
    planCode: 'earlier-expiry',
    startsAt: '2026-06-01T00:00:00+00:00',
    expiresAt: '2026-09-01T00:00:00+00:00',
    scopeType: 'all',
    scopeSnapshot: ['type' => 'all'],
    quotaSnapshot: ['limit' => 10, 'remaining' => 5],
    priority: 100,
));
$tieBest = (new MembershipService($tieRepo))->chooseBestForResource(
    userId: 1001,
    resourceId: 1100,
    taxonomyTermIds: [],
    atUtc: '2026-06-29T10:00:00+00:00',
);
sr048_same($laterExpiry->id, $tieBest['entitlement']->id, 'same coverage quota and priority chooses later expiry before id');

echo "SR-048 membership renewal checks passed.\n";
