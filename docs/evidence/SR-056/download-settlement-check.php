<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$package = $root.'/packages/sr-private-downloads';
$entitlementsPackage = $root.'/packages/sr-entitlements';

require_once $entitlementsPackage.'/src/Application/QuotaService.php';
require_once $package.'/src/Application/DownloadSettlementService.php';

use StockResource\PrivateDownloads\Application\DownloadEventRecord;
use StockResource\PrivateDownloads\Application\DownloadReconcileCommand;
use StockResource\PrivateDownloads\Application\DownloadReconcileRequest;
use StockResource\PrivateDownloads\Application\DownloadSettlementService;
use StockResource\PrivateDownloads\Application\DownloadTokenSettlementRecord;
use StockResource\PrivateDownloads\Application\InMemoryDownloadSettlementRepository;
use StockResource\PrivateDownloads\Application\QuotaServiceSettlementGateway;
use StockResource\PrivateDownloads\Application\RecordingDownloadSettlementTransactionRunner;
use StockResource\PrivateDownloads\Application\RecordingSettlementClock;
use StockResource\PrivateDownloads\Application\RecordingSettlementNotifier;
use StockResource\PrivateDownloads\Application\RecordingSettlementQuotaGateway;
use StockResource\Entitlements\Application\InMemoryQuotaCounterStore;
use StockResource\Entitlements\Application\QuotaService;

function sr056_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "ASSERTION FAILED: {$message} expected=".var_export($expected, true).' actual='.var_export($actual, true)."\n");
        exit(1);
    }
}

function sr056_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

$now = '2026-06-30T00:05:00+00:00';
$clock = new RecordingSettlementClock($now);
$repository = new InMemoryDownloadSettlementRepository();
$quota = new RecordingSettlementQuotaGateway();
$notifier = new RecordingSettlementNotifier();
$transactions = new RecordingDownloadSettlementTransactionRunner();
$service = new DownloadSettlementService($repository, $quota, $notifier, $clock, $transactions);

$repository->saveToken(new DownloadTokenSettlementRecord(
    tokenId: 1,
    requestId: '11111111-1111-4111-8111-111111111111',
    userId: 101,
    entitlementId: 501,
    resourceId: 88,
    versionId: 7,
    accessSource: 'vip',
    quotaReservationId: 'quota-1',
    status: 'consumed',
    expiresAt: '2026-06-30T00:10:00+00:00',
    redirectedAt: '2026-06-30T00:04:30+00:00',
));

$settled = $service->settleRedirected('11111111-1111-4111-8111-111111111111');
sr056_same(true, $settled->ok, 'redirected settlement succeeds');
sr056_same('settled_redirected', $settled->status, 'redirected settlement returns stable status');
sr056_same(['commit:quota-1:11111111-1111-4111-8111-111111111111'], $quota->events, 'redirected settlement commits quota exactly once');
$event = $repository->eventByRequestId('11111111-1111-4111-8111-111111111111');
sr056_assert($event instanceof DownloadEventRecord, 'redirected settlement writes event');
sr056_same('redirected', $event->result, 'redirected event result is stored');
sr056_same(true, $event->counted, 'redirected event is counted');
sr056_same(null, $event->errorCode, 'redirected event has no error code');
sr056_same(['begin', 'commit'], $transactions->events, 'redirected settlement runs inside transaction');

$settledAgain = $service->settleRedirected('11111111-1111-4111-8111-111111111111');
sr056_same(true, $settledAgain->ok, 'redirected replay is ok');
sr056_same('already_settled', $settledAgain->status, 'redirected replay is idempotent');
sr056_same(['commit:quota-1:11111111-1111-4111-8111-111111111111'], $quota->events, 'redirected replay does not double commit quota');

$repository->saveToken(new DownloadTokenSettlementRecord(
    tokenId: 2,
    requestId: '22222222-2222-4222-8222-222222222222',
    userId: 101,
    entitlementId: 501,
    resourceId: 88,
    versionId: 7,
    accessSource: 'vip',
    quotaReservationId: 'quota-2',
    status: 'failed',
    expiresAt: '2026-06-30T00:10:00+00:00',
    failureCode: 'object_not_found',
));

$failed = $service->settleFailed('22222222-2222-4222-8222-222222222222', 'object_not_found');
sr056_same(true, $failed->ok, 'failed settlement succeeds');
sr056_same('released_failed', $failed->status, 'failed settlement returns release status');
sr056_same('release:quota-2:22222222-2222-4222-8222-222222222222', $quota->events[1] ?? null, 'failed settlement releases quota');
$failedEvent = $repository->eventByRequestId('22222222-2222-4222-8222-222222222222');
sr056_assert($failedEvent instanceof DownloadEventRecord, 'failed settlement writes event');
sr056_same('failed', $failedEvent->result, 'failed event result is stored');
sr056_same(false, $failedEvent->counted, 'failed event is not counted');
sr056_same('object_not_found', $failedEvent->errorCode, 'failed event stores sanitized failure code');
sr056_same(['begin', 'commit', 'begin', 'commit'], $transactions->events, 'failed settlement runs inside transaction');

$failedAgain = $service->settleFailed('22222222-2222-4222-8222-222222222222', 'object_not_found');
sr056_same('already_settled', $failedAgain->status, 'failed replay is idempotent');
sr056_same(2, count($quota->events), 'failed replay does not double release quota');

$repository->saveToken(new DownloadTokenSettlementRecord(
    tokenId: 3,
    requestId: '33333333-3333-4333-8333-333333333333',
    userId: 101,
    entitlementId: 501,
    resourceId: 89,
    versionId: 8,
    accessSource: 'vip',
    quotaReservationId: 'quota-3',
    status: 'issued',
    expiresAt: '2026-06-30T00:04:00+00:00',
));

$dryRun = $service->reconcile(new DownloadReconcileRequest(
    requestId: '33333333-3333-4333-8333-333333333333',
    dryRun: true,
));
sr056_same(true, $dryRun->ok, 'dry-run reconcile succeeds');
sr056_same('would_release_expired', $dryRun->status, 'dry-run reports intended expired-token release');
sr056_same(2, count($quota->events), 'dry-run does not change quota');
sr056_same(null, $repository->eventByRequestId('33333333-3333-4333-8333-333333333333'), 'dry-run does not write event');

$expired = $service->reconcile(new DownloadReconcileRequest(
    requestId: '33333333-3333-4333-8333-333333333333',
    dryRun: false,
));
sr056_same(true, $expired->ok, 'expired reconcile succeeds');
sr056_same('released_expired', $expired->status, 'expired reconcile releases reservation');
sr056_same('expired', $repository->tokenByRequestId('33333333-3333-4333-8333-333333333333')?->status, 'expired reconcile marks token expired');
sr056_same('release:quota-3:33333333-3333-4333-8333-333333333333', $quota->events[2] ?? null, 'expired reconcile releases quota');
$expiredEvent = $repository->eventByRequestId('33333333-3333-4333-8333-333333333333');
sr056_assert($expiredEvent instanceof DownloadEventRecord, 'expired reconcile writes event');
sr056_same('failed', $expiredEvent->result, 'expired reconcile writes failed event');
sr056_same('token_expired', $expiredEvent->errorCode, 'expired reconcile stores stable failure code');
sr056_same(['begin', 'commit', 'begin', 'commit', 'begin', 'commit'], $transactions->events, 'expired reconcile runs inside transaction');

$expiredAgain = $service->reconcile(new DownloadReconcileRequest(
    requestId: '33333333-3333-4333-8333-333333333333',
    dryRun: false,
));
sr056_same('already_settled', $expiredAgain->status, 'expired replay is idempotent after event exists');
sr056_same(3, count($quota->events), 'expired replay does not double release quota');

$repository->saveToken(new DownloadTokenSettlementRecord(
    tokenId: 3,
    requestId: '55555555-5555-4555-8555-555555555555',
    userId: 101,
    entitlementId: 501,
    resourceId: 89,
    versionId: 8,
    accessSource: 'vip',
    quotaReservationId: 'quota-duplicate-token',
    status: 'consumed',
    expiresAt: '2026-06-30T00:10:00+00:00',
    redirectedAt: '2026-06-30T00:05:00+00:00',
));
$duplicateTokenReplay = $service->settleRedirected('55555555-5555-4555-8555-555555555555');
sr056_same('already_settled', $duplicateTokenReplay->status, 'same token_id with new request_id is treated as already settled');
sr056_same(3, count($quota->events), 'same token_id replay does not mutate quota');

sr056_assert(! str_contains(json_encode($repository->events, JSON_THROW_ON_ERROR), 'raw-token'), 'event store does not contain raw token');
sr056_assert($notifier->events === [], 'happy-path reconciliation produces no alert');

$missing = $service->reconcile(new DownloadReconcileRequest(
    requestId: '44444444-4444-4444-8444-444444444444',
    dryRun: false,
));
sr056_same(false, $missing->ok, 'missing token reconcile fails');
sr056_same('token_not_found', $missing->status, 'missing token has stable status');
sr056_same(['alert:token_not_found:44444444-4444-4444-8444-444444444444'], $notifier->events, 'missing token emits alert');

$retryRepository = new InMemoryDownloadSettlementRepository();
$retryNotifier = new RecordingSettlementNotifier();
$retryRepository->saveToken(new DownloadTokenSettlementRecord(
    tokenId: 4,
    requestId: '66666666-6666-4666-8666-666666666666',
    userId: 101,
    entitlementId: 501,
    resourceId: 90,
    versionId: 9,
    accessSource: 'vip',
    quotaReservationId: 'quota-retry',
    status: 'issued',
    expiresAt: '2026-06-30T00:04:00+00:00',
));
$retryFail = (new DownloadSettlementService(
    repository: $retryRepository,
    quota: new RecordingSettlementQuotaGateway('release_down'),
    notifier: $retryNotifier,
    clock: $clock,
    transactions: new RecordingDownloadSettlementTransactionRunner(),
))->reconcile(new DownloadReconcileRequest('66666666-6666-4666-8666-666666666666', false));
sr056_same(false, $retryFail->ok, 'expired release failure fails closed');
sr056_same('release_down', $retryFail->status, 'expired release failure returns quota status');
sr056_same('issued', $retryRepository->tokenByRequestId('66666666-6666-4666-8666-666666666666')?->status, 'expired release failure leaves token retryable');
sr056_same(null, $retryRepository->eventByRequestId('66666666-6666-4666-8666-666666666666'), 'expired release failure does not write event');
sr056_same(['alert:release_down:66666666-6666-4666-8666-666666666666'], $retryNotifier->events, 'expired release failure emits alert');
$retrySuccess = (new DownloadSettlementService(
    repository: $retryRepository,
    quota: new RecordingSettlementQuotaGateway(),
    notifier: $retryNotifier,
    clock: $clock,
    transactions: new RecordingDownloadSettlementTransactionRunner(),
))->reconcile(new DownloadReconcileRequest('66666666-6666-4666-8666-666666666666', false));
sr056_same(true, $retrySuccess->ok, 'expired release retry succeeds after prior failure');
sr056_same('released_expired', $retrySuccess->status, 'expired release retry releases quota');

$quotaStore = new InMemoryQuotaCounterStore();
$quotaService = new QuotaService($quotaStore);
$commitReserve = $quotaService->reserve(
    entitlementId: 701,
    userId: 201,
    periodType: 'month',
    periodKey: '2026-06',
    limit: 3,
    requestId: '77777777-7777-4777-8777-777777777777',
    nowUtc: '2026-06-30T00:00:00+00:00',
);
sr056_same(true, $commitReserve->ok, 'real quota reserve succeeds before redirected settlement');
$realRepository = new InMemoryDownloadSettlementRepository();
$realRepository->saveToken(new DownloadTokenSettlementRecord(
    tokenId: 5,
    requestId: '77777777-7777-4777-8777-777777777777',
    userId: 201,
    entitlementId: 701,
    resourceId: 91,
    versionId: 10,
    accessSource: 'vip',
    quotaReservationId: (string) $commitReserve->reservationId,
    status: 'consumed',
    expiresAt: '2026-06-30T00:10:00+00:00',
    redirectedAt: '2026-06-30T00:05:00+00:00',
));
$realCommit = (new DownloadSettlementService(
    repository: $realRepository,
    quota: new QuotaServiceSettlementGateway($quotaService),
    notifier: new RecordingSettlementNotifier(),
    clock: $clock,
    transactions: new RecordingDownloadSettlementTransactionRunner(),
))->settleRedirected('77777777-7777-4777-8777-777777777777');
sr056_same(true, $realCommit->ok, 'real quota redirected settlement succeeds');
$commitCounter = $quotaStore->counterSnapshot(701, 'month', '2026-06');
sr056_same(1, $commitCounter['used_count'] ?? null, 'real quota commit increments used_count');
sr056_same(0, $commitCounter['reserved_count'] ?? null, 'real quota commit decrements reserved_count');

$releaseReserve = $quotaService->reserve(
    entitlementId: 701,
    userId: 201,
    periodType: 'month',
    periodKey: '2026-06',
    limit: 3,
    requestId: '88888888-8888-4888-8888-888888888888',
    nowUtc: '2026-06-30T00:01:00+00:00',
);
sr056_same(true, $releaseReserve->ok, 'real quota reserve succeeds before failed settlement');
$realRepository->saveToken(new DownloadTokenSettlementRecord(
    tokenId: 6,
    requestId: '88888888-8888-4888-8888-888888888888',
    userId: 201,
    entitlementId: 701,
    resourceId: 91,
    versionId: 10,
    accessSource: 'vip',
    quotaReservationId: (string) $releaseReserve->reservationId,
    status: 'failed',
    expiresAt: '2026-06-30T00:10:00+00:00',
    failureCode: 'object_not_found',
));
$realRelease = (new DownloadSettlementService(
    repository: $realRepository,
    quota: new QuotaServiceSettlementGateway($quotaService),
    notifier: new RecordingSettlementNotifier(),
    clock: $clock,
    transactions: new RecordingDownloadSettlementTransactionRunner(),
))->settleFailed('88888888-8888-4888-8888-888888888888', 'object_not_found');
sr056_same(true, $realRelease->ok, 'real quota failed settlement succeeds');
$releaseCounter = $quotaStore->counterSnapshot(701, 'month', '2026-06');
sr056_same(1, $releaseCounter['used_count'] ?? null, 'real quota release does not increment used_count');
sr056_same(0, $releaseCounter['reserved_count'] ?? null, 'real quota release decrements reserved_count');

$command = new DownloadReconcileCommand($service);
$commandResult = $command(['33333333-3333-4333-8333-333333333333'], ['dry-run' => true]);
sr056_same('sr downloads:reconcile', $commandResult['command'] ?? null, 'CLI command adapter exposes expected command name');
sr056_same(true, $commandResult['dry_run'] ?? null, 'CLI command adapter defaults/supports dry-run');
sr056_same('already_settled', $commandResult['status'] ?? null, 'CLI command adapter returns reconciliation status');

echo "SR-056 download settlement checks passed\n";
