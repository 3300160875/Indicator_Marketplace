<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$iterations = max(1, (int) ($argv[1] ?? 20));

require_once $root.'/packages/sr-contracts/src/Entitlement/AccessDecision.php';
require_once $root.'/packages/sr-contracts/src/Entitlement/AccessDecisionContext.php';
require_once $root.'/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementStatus.php';
require_once $root.'/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementException.php';
require_once $root.'/packages/sr-entitlements/src/Infrastructure/Repository/Entitlement.php';
require_once $root.'/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementRepository.php';
require_once $root.'/packages/sr-entitlements/src/Infrastructure/Repository/InMemoryEntitlementRepository.php';
require_once $root.'/packages/sr-entitlements/src/Application/EntitlementService.php';
require_once $root.'/packages/sr-entitlements/src/Application/QuotaService.php';
require_once $root.'/packages/sr-entitlements/src/Rest/Me/MeEntitlementsController.php';
require_once $root.'/packages/sr-private-downloads/src/Token/DownloadTokenService.php';
require_once $root.'/packages/sr-private-downloads/src/Rest/CreateDownloadTokenController.php';

use StockResource\Entitlements\Infrastructure\Repository\Entitlement;
use StockResource\Entitlements\Infrastructure\Repository\InMemoryEntitlementRepository;
use StockResource\Entitlements\Rest\Me\InMemoryMeEntitlementsCacheStore;
use StockResource\Entitlements\Rest\Me\MeEntitlementsController;
use StockResource\PrivateDownloads\Rest\CreateDownloadTokenController;
use StockResource\PrivateDownloads\Rest\CreateDownloadTokenRequest;
use StockResource\PrivateDownloads\Rest\InMemoryCreateDownloadTokenIdempotencyStore;
use StockResource\PrivateDownloads\Rest\RecordingAccessDecisionGateway;
use StockResource\PrivateDownloads\Rest\RecordingQuotaGateway;
use StockResource\PrivateDownloads\Rest\RecordingTransactionRunner;
use StockResource\PrivateDownloads\Token\DownloadTokenService;
use StockResource\PrivateDownloads\Token\FixedTokenBytes;
use StockResource\PrivateDownloads\Token\InMemoryDownloadTokenRepository;

function sr068_entitlement(int $userId, string $planCode): Entitlement
{
    return Entitlement::fromSnapshot(
        userId: $userId,
        eddCustomerId: 9000 + $userId,
        grantType: 'membership',
        sourceType: 'edd',
        sourceOrderId: 7000 + $userId,
        sourceOrderItemId: null,
        planDownloadId: 5001,
        parentEntitlementId: null,
        resourceId: null,
        scopeType: 'all',
        scopeSnapshot: [
            'type' => 'all',
            'plan' => ['code' => $planCode, 'name' => strtoupper($planCode)],
            'resource_ids' => [],
        ],
        quotaSnapshot: [
            'period_type' => 'month',
            'period_key' => '2026-07',
            'limit' => 20,
            'used' => 4,
            'reserved' => 1,
            'remaining' => 15,
            'reset_at' => '2026-08-01T00:00:00+00:00',
        ],
        rulesVersion: 'rules-v3',
        startsAt: '2026-07-01T00:00:00+00:00',
        expiresAt: '2026-12-31T23:59:59+00:00',
        priority: 10,
        createdBy: 1,
        createdAt: '2026-07-01T00:00:00+00:00',
        updatedAt: '2026-07-01T00:00:00+00:00',
    );
}

function sr068_ms(callable $callback): float
{
    $start = hrtime(true);
    $callback();

    return round((hrtime(true) - $start) / 1_000_000, 3);
}

$repository = new InMemoryEntitlementRepository();
for ($user = 101; $user <= 103; $user++) {
    for ($row = 1; $row <= 4; $row++) {
        $repository->create(sr068_entitlement($user, 'vip-'.$row));
    }
}

$cache = new InMemoryMeEntitlementsCacheStore();
$meController = new MeEntitlementsController($repository, $cache, static fn (): string => 'rules-v3');

$entitlementMs = [];
$tokenMs = [];
$cacheKeys = [];

for ($i = 0; $i < $iterations; $i++) {
    $userId = $i % 2 === 0 ? 101 : 102;
    $meController->invalidateForUser($userId);
    $entitlementMs[] = sr068_ms(static function () use ($meController, $userId): void {
        $response = $meController->show($userId, '2026-07-01T00:00:00+00:00');
        if (($response['user_id'] ?? null) !== $userId) {
            throw new RuntimeException('me_entitlements user scope mismatch');
        }
    });
    $cacheKeys[] = MeEntitlementsController::cacheKey($userId, 'rules-v3');

    $tokenBytes = str_repeat(chr(65 + ($i % 26)), 32);
    $tokenController = new CreateDownloadTokenController(
        new RecordingAccessDecisionGateway(source: 'VIP', entitlementId: 501 + $i),
        new RecordingQuotaGateway('quota-reserved-'.$i),
        new DownloadTokenService(
            new InMemoryDownloadTokenRepository(),
            'local-app-key-for-hmac-tests',
            new FixedTokenBytes($tokenBytes),
        ),
        new InMemoryCreateDownloadTokenIdempotencyStore(),
        new RecordingTransactionRunner(),
    );
    $tokenMs[] = sr068_ms(static function () use ($tokenController, $i): void {
        $response = $tokenController->create(new CreateDownloadTokenRequest(
            idempotencyKey: 'idem-sr068-'.$i,
            requestId: 'download-request-sr068-'.$i,
            userId: 101,
            resourceId: 88,
            versionId: 7,
            accessMode: 'vip',
            resourceStatus: 'published',
            source: 'performance',
            nowUtc: '2026-07-01T00:00:00+00:00',
        ));
        if ($response->statusCode !== 201) {
            throw new RuntimeException('download token API timing request failed');
        }
        if (isset($response->body['storage_key'], $response->body['signed_url'])) {
            throw new RuntimeException('download token response leaked private storage data');
        }
    });
}

echo json_encode([
    'source' => 'tests/performance/sr068-api-timing.php',
    'iterations' => $iterations,
    'entitlement_api_ms' => $entitlementMs,
    'download_token_api_ms' => $tokenMs,
    'cache_trace' => [
        'me_entitlements_distinct_user_keys' => count(array_unique($cacheKeys)) >= 2,
        'me_entitlements_key_parts' => ['user_id', 'rules_version'],
        'download_token_response_leak_checked' => true,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
