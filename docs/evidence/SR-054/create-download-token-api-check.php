<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$package = $root.'/packages/sr-private-downloads';

require_once $root.'/packages/sr-contracts/src/Entitlement/AccessDecision.php';
require_once $root.'/packages/sr-contracts/src/Entitlement/AccessDecisionContext.php';
require_once $root.'/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementStatus.php';
require_once $root.'/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementException.php';
require_once $root.'/packages/sr-entitlements/src/Infrastructure/Repository/Entitlement.php';
require_once $root.'/packages/sr-entitlements/src/Infrastructure/Repository/EntitlementRepository.php';
require_once $root.'/packages/sr-entitlements/src/Infrastructure/Repository/InMemoryEntitlementRepository.php';
require_once $root.'/packages/sr-entitlements/src/Application/EntitlementService.php';
require_once $root.'/packages/sr-entitlements/src/Application/QuotaService.php';
require_once $package.'/src/Token/DownloadTokenService.php';
require_once $package.'/src/Rest/CreateDownloadTokenController.php';

use StockResource\Entitlements\Application\EntitlementService;
use StockResource\Entitlements\Application\InMemoryQuotaCounterStore;
use StockResource\Entitlements\Application\QuotaService;
use StockResource\Entitlements\Infrastructure\Repository\InMemoryEntitlementRepository;
use StockResource\PrivateDownloads\Rest\CreateDownloadTokenController;
use StockResource\PrivateDownloads\Rest\CreateDownloadTokenRequest;
use StockResource\PrivateDownloads\Rest\EntitlementServiceAccessDecisionGateway;
use StockResource\PrivateDownloads\Rest\InMemoryCreateDownloadTokenIdempotencyStore;
use StockResource\PrivateDownloads\Rest\QuotaServiceReservationGateway;
use StockResource\PrivateDownloads\Rest\RecordingAccessDecisionGateway;
use StockResource\PrivateDownloads\Rest\RecordingQuotaGateway;
use StockResource\PrivateDownloads\Rest\RecordingTransactionRunner;
use StockResource\PrivateDownloads\Token\DownloadTokenService;
use StockResource\PrivateDownloads\Token\FixedTokenBytes;
use StockResource\PrivateDownloads\Token\InMemoryDownloadTokenRepository;

function sr054_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function sr054_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "ASSERTION FAILED: {$message} expected=".var_export($expected, true).' actual='.var_export($actual, true)."\n");
        exit(1);
    }
}

$tokenRepository = new InMemoryDownloadTokenRepository();
$tokenService = new DownloadTokenService(
    repository: $tokenRepository,
    appKey: 'local-app-key-for-hmac-tests',
    tokenBytes: new FixedTokenBytes(str_repeat("\x44", 32)),
);
$access = new RecordingAccessDecisionGateway(source: 'VIP', entitlementId: 501);
$quota = new RecordingQuotaGateway(reservationId: 'quota-reserved-1');
$transactions = new RecordingTransactionRunner();
$idempotency = new InMemoryCreateDownloadTokenIdempotencyStore();
$controller = new CreateDownloadTokenController($access, $quota, $tokenService, $idempotency, $transactions);

$request = new CreateDownloadTokenRequest(
    idempotencyKey: 'idem-1',
    requestId: 'download-request-1',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    accessMode: 'vip',
    resourceStatus: 'published',
    source: 'account',
    nowUtc: '2026-06-30T00:00:00+00:00',
);
$response = $controller->create($request);

sr054_same(201, $response->statusCode, 'first request creates token');
sr054_same('created', $response->body['status'] ?? null, 'response status is created');
sr054_assert(isset($response->body['download_token']), 'response returns one-time raw token');
sr054_same(120, $response->body['ttl_seconds'] ?? null, 'response exposes TTL');
sr054_same('2026-06-30T00:02:00+00:00', $response->body['expires_at'] ?? null, 'response exposes expiry');
sr054_assert(! isset($response->body['storage_key']), 'response must not expose storage_key');
sr054_assert(! isset($response->body['signed_url']), 'response must not expose signed URL');
sr054_same(['begin', 'commit'], $transactions->events, 'token flow runs inside transaction');
sr054_same(['decide', 'reserve', 'issue'], $controller->events(), 'VIP flow decides, reserves, then issues token');
sr054_same(1, $quota->reserveCalls, 'VIP source reserves quota');

$stored = $tokenRepository->findByRequestId('download-request-1');
sr054_assert($stored !== null, 'token record is stored');
sr054_same('quota-reserved-1', $stored->quotaReservationId, 'token binds reserved quota');
sr054_same(501, $stored->entitlementId, 'token binds access decision entitlement');

$repeat = $controller->create($request);
sr054_same(200, $repeat->statusCode, 'same idempotency key and body returns stored response');
sr054_same($response->body, $repeat->body, 'idempotent repeat returns same token result');
sr054_same(1, $quota->reserveCalls, 'idempotent repeat does not reserve quota twice');

$conflict = $controller->create(new CreateDownloadTokenRequest(
    idempotencyKey: 'idem-1',
    requestId: 'download-request-2',
    userId: 101,
    resourceId: 99,
    versionId: 7,
    accessMode: 'vip',
    resourceStatus: 'published',
    source: 'account',
    nowUtc: '2026-06-30T00:00:00+00:00',
));
sr054_same(409, $conflict->statusCode, 'same idempotency key with different body conflicts');
sr054_same('idempotency_conflict', $conflict->body['code'] ?? null, 'conflict exposes stable error code');

$freeQuota = new RecordingQuotaGateway(reservationId: 'should-not-reserve');
$freeController = new CreateDownloadTokenController(
    new RecordingAccessDecisionGateway(source: 'FREE', entitlementId: 1),
    $freeQuota,
    new DownloadTokenService(new InMemoryDownloadTokenRepository(), 'local-app-key-for-hmac-tests', new FixedTokenBytes(str_repeat("\x55", 32))),
    new InMemoryCreateDownloadTokenIdempotencyStore(),
    new RecordingTransactionRunner(),
);
$free = $freeController->create(new CreateDownloadTokenRequest(
    idempotencyKey: 'idem-free',
    requestId: 'download-request-free',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    accessMode: 'free',
    resourceStatus: 'published',
    source: 'account',
    nowUtc: '2026-06-30T00:00:00+00:00',
));
sr054_same(201, $free->statusCode, 'free source can create token');
sr054_same(0, $freeQuota->reserveCalls, 'free source does not reserve VIP quota');

$freeRealTokenRepo = new InMemoryDownloadTokenRepository();
$freeRealController = new CreateDownloadTokenController(
    new EntitlementServiceAccessDecisionGateway(new EntitlementService(new InMemoryEntitlementRepository())),
    new RecordingQuotaGateway('should-not-reserve'),
    new DownloadTokenService($freeRealTokenRepo, 'local-app-key-for-hmac-tests', new FixedTokenBytes(str_repeat("\x56", 32))),
    new InMemoryCreateDownloadTokenIdempotencyStore(),
    new RecordingTransactionRunner(),
);
$freeReal = $freeRealController->create(new CreateDownloadTokenRequest(
    idempotencyKey: 'idem-free-real',
    requestId: 'download-request-free-real',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    accessMode: 'free',
    resourceStatus: 'published',
    source: 'account',
    nowUtc: '2026-06-30T00:00:00+00:00',
));
sr054_same(201, $freeReal->statusCode, 'real EntitlementService FREE decision can create token');
$freeRealStored = $freeRealTokenRepo->findByRequestId('download-request-free-real');
sr054_assert($freeRealStored !== null, 'real free token is stored');
sr054_same(null, $freeRealStored->entitlementId, 'real FREE token stores null entitlement');

$quotaServiceTokenRepo = new InMemoryDownloadTokenRepository();
$quotaServiceController = new CreateDownloadTokenController(
    new RecordingAccessDecisionGateway(source: 'VIP', entitlementId: 777),
    new QuotaServiceReservationGateway(new QuotaService(new InMemoryQuotaCounterStore())),
    new DownloadTokenService($quotaServiceTokenRepo, 'local-app-key-for-hmac-tests', new FixedTokenBytes(str_repeat("\x57", 32))),
    new InMemoryCreateDownloadTokenIdempotencyStore(),
    new RecordingTransactionRunner(),
);
$quotaServiceResponse = $quotaServiceController->create(new CreateDownloadTokenRequest(
    idempotencyKey: 'idem-quota-service',
    requestId: 'download-request-quota-service',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    accessMode: 'vip',
    resourceStatus: 'published',
    source: 'account',
    nowUtc: '2026-06-30T00:00:00+00:00',
));
sr054_same(201, $quotaServiceResponse->statusCode, 'QuotaService adapter can reserve and issue token');
$quotaServiceStored = $quotaServiceTokenRepo->findByRequestId('download-request-quota-service');
sr054_assert($quotaServiceStored !== null, 'QuotaService-backed token is stored');
sr054_assert($quotaServiceStored->quotaReservationId !== 'none', 'QuotaService-backed token binds real reservation');

$quotaFailRepo = new InMemoryDownloadTokenRepository();
$quotaFail = (new CreateDownloadTokenController(
    new RecordingAccessDecisionGateway(source: 'VIP', entitlementId: 501),
    new RecordingQuotaGateway(''),
    new DownloadTokenService($quotaFailRepo, 'local-app-key-for-hmac-tests', new FixedTokenBytes(str_repeat("\x58", 32))),
    new InMemoryCreateDownloadTokenIdempotencyStore(),
    new RecordingTransactionRunner(),
))->create(new CreateDownloadTokenRequest(
    idempotencyKey: 'idem-quota-fail',
    requestId: 'download-request-quota-fail',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    accessMode: 'vip',
    resourceStatus: 'published',
    source: 'account',
    nowUtc: '2026-06-30T00:00:00+00:00',
));
sr054_same(409, $quotaFail->statusCode, 'quota reserve failure fails closed');
sr054_same('quota_exhausted', $quotaFail->body['code'] ?? null, 'quota reserve failure returns stable error code');
sr054_same(null, $quotaFailRepo->findByRequestId('download-request-quota-fail'), 'quota failure must not issue token');
$quotaFailReplay = (new CreateDownloadTokenController(
    new RecordingAccessDecisionGateway(source: 'VIP', entitlementId: 501),
    new RecordingQuotaGateway(''),
    new DownloadTokenService(new InMemoryDownloadTokenRepository(), 'local-app-key-for-hmac-tests', new FixedTokenBytes(str_repeat("\x58", 32))),
    new InMemoryCreateDownloadTokenIdempotencyStore(),
    new RecordingTransactionRunner(),
));
$quotaFailReplayRequest = new CreateDownloadTokenRequest(
    idempotencyKey: 'idem-quota-fail-replay',
    requestId: 'download-request-quota-fail-replay',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    accessMode: 'vip',
    resourceStatus: 'published',
    source: 'account',
    nowUtc: '2026-06-30T00:00:00+00:00',
);
$quotaFailReplayFirst = $quotaFailReplay->create($quotaFailReplayRequest);
$quotaFailReplaySecond = $quotaFailReplay->create($quotaFailReplayRequest);
sr054_same(409, $quotaFailReplayFirst->statusCode, 'quota failure replay setup fails first request');
sr054_same($quotaFailReplayFirst->statusCode, $quotaFailReplaySecond->statusCode, 'quota failure replay returns same status');
sr054_same($quotaFailReplayFirst->body, $quotaFailReplaySecond->body, 'quota failure replay returns same body');

$pendingRequest = new CreateDownloadTokenRequest(
    idempotencyKey: 'idem-pending',
    requestId: 'download-request-pending',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    accessMode: 'vip',
    resourceStatus: 'published',
    source: 'account',
    nowUtc: '2026-06-30T00:00:00+00:00',
);
$pendingStore = new InMemoryCreateDownloadTokenIdempotencyStore();
$pendingStore->claim($pendingRequest->idempotencyKey, $pendingRequest->fingerprint());
$pendingQuota = new RecordingQuotaGateway('quota-pending');
$pending = (new CreateDownloadTokenController(
    new RecordingAccessDecisionGateway(source: 'VIP', entitlementId: 501),
    $pendingQuota,
    new DownloadTokenService(new InMemoryDownloadTokenRepository(), 'local-app-key-for-hmac-tests', new FixedTokenBytes(str_repeat("\x59", 32))),
    $pendingStore,
    new RecordingTransactionRunner(),
))->create($pendingRequest);
sr054_same(409, $pending->statusCode, 'in-progress idempotency claim fails closed');
sr054_same('idempotency_in_progress', $pending->body['code'] ?? null, 'in-progress idempotency has stable error code');
sr054_same(0, $pendingQuota->reserveCalls, 'in-progress idempotency does not reserve quota');

$deniedController = new CreateDownloadTokenController(
    new RecordingAccessDecisionGateway(source: null, entitlementId: null, allowed: false, reason: 'no_entitlement'),
    new RecordingQuotaGateway('unused'),
    new DownloadTokenService(new InMemoryDownloadTokenRepository(), 'local-app-key-for-hmac-tests', new FixedTokenBytes(str_repeat("\x66", 32))),
    new InMemoryCreateDownloadTokenIdempotencyStore(),
    new RecordingTransactionRunner(),
);
$deniedRequest = new CreateDownloadTokenRequest(
    idempotencyKey: 'idem-denied',
    requestId: 'download-request-denied',
    userId: 101,
    resourceId: 88,
    versionId: 7,
    accessMode: 'vip',
    resourceStatus: 'published',
    source: 'account',
    nowUtc: '2026-06-30T00:00:00+00:00',
);
$denied = $deniedController->create($deniedRequest);
$deniedRepeat = $deniedController->create($deniedRequest);
sr054_same(403, $denied->statusCode, 'denied access fails closed');
sr054_same('no_entitlement', $denied->body['code'] ?? null, 'denied access returns stable reason');
sr054_same($denied->statusCode, $deniedRepeat->statusCode, 'denied replay returns same status');
sr054_same($denied->body, $deniedRepeat->body, 'denied replay returns same body');

echo "SR-054 create download token API checks passed\n";
