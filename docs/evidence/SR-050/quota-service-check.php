<?php
declare(strict_types=1);

use StockResource\Entitlements\Application\InMemoryQuotaCounterStore;
use StockResource\Entitlements\Application\QuotaCounterRecord;
use StockResource\Entitlements\Application\QuotaCounterStore;
use StockResource\Entitlements\Application\QuotaOperationResult;
use StockResource\Entitlements\Application\QuotaReservationRecord;
use StockResource\Entitlements\Application\QuotaService;

$root = dirname(__DIR__, 3);

require_once $root.'/packages/sr-entitlements/src/Application/QuotaService.php';

function sr050_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr050_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr050_reserve(
    QuotaService $service,
    int $entitlementId,
    string $requestId,
    int $limit = 1,
    string $periodType = 'month',
    string $periodKey = '2026-06',
): QuotaOperationResult {
    return $service->reserve(
        entitlementId: $entitlementId,
        userId: 1001,
        periodType: $periodType,
        periodKey: $periodKey,
        limit: $limit,
        requestId: $requestId,
        nowUtc: '2026-06-29T10:00:00+00:00',
    );
}

final class Sr050InterleavingQuotaStore implements QuotaCounterStore
{
    private InMemoryQuotaCounterStore $inner;
    public mixed $beforeLockedCallback = null;

    public function __construct()
    {
        $this->inner = new InMemoryQuotaCounterStore();
    }

    public function withCounterForUpdate(
        int $entitlementId,
        int $userId,
        string $periodType,
        string $periodKey,
        int $limit,
        string $nowUtc,
        callable $callback,
    ): QuotaOperationResult {
        return $this->inner->withCounterForUpdate(
            entitlementId: $entitlementId,
            userId: $userId,
            periodType: $periodType,
            periodKey: $periodKey,
            limit: $limit,
            nowUtc: $nowUtc,
            callback: function (QuotaCounterRecord $counter) use ($callback): QuotaOperationResult {
                if (is_callable($this->beforeLockedCallback)) {
                    $hook = $this->beforeLockedCallback;
                    $this->beforeLockedCallback = null;
                    $hook();
                }

                return $callback($counter);
            },
        );
    }

    public function findReservation(string $reservationId): ?QuotaReservationRecord
    {
        return $this->inner->findReservation($reservationId);
    }

    public function findReservationByRequestId(string $requestId): ?QuotaReservationRecord
    {
        return $this->inner->findReservationByRequestId($requestId);
    }

    public function saveReservation(QuotaReservationRecord $reservation): void
    {
        $this->inner->saveReservation($reservation);
    }

    public function counterSnapshot(int $entitlementId, string $periodType, string $periodKey): array
    {
        return $this->inner->counterSnapshot($entitlementId, $periodType, $periodKey);
    }
}

$store = new InMemoryQuotaCounterStore();
$service = new QuotaService($store);

$first = sr050_reserve($service, 501, 'req-1', limit: 1);
sr050_same(true, $first->ok, 'first reservation succeeds when one quota remains');
sr050_same('reserved', $first->status, 'first reservation status');
sr050_same(0, $first->counter['used_count'] ?? null, 'reserve does not increment used count');
sr050_same(1, $first->counter['reserved_count'] ?? null, 'reserve increments reserved count');

$duplicate = sr050_reserve($service, 501, 'req-1', limit: 1);
sr050_same(true, $duplicate->ok, 'duplicate reserve request is idempotent');
sr050_same($first->reservationId, $duplicate->reservationId, 'duplicate reserve returns same reservation id');
sr050_same(1, $duplicate->counter['reserved_count'] ?? null, 'duplicate reserve does not double reserve');

$blocked = sr050_reserve($service, 501, 'req-2', limit: 1);
sr050_same(false, $blocked->ok, 'second request is denied when only one quota was available');
sr050_same('quota_exhausted', $blocked->status, 'quota exhausted status is stable');
sr050_same(1, $blocked->counter['reserved_count'] ?? null, 'denied reserve does not change reserved count');

$committed = $service->commit($first->reservationId, 'req-1', '2026-06-29T10:01:00+00:00');
sr050_same(true, $committed->ok, 'commit succeeds for pending reservation');
sr050_same('committed', $committed->status, 'commit status');
sr050_same(1, $committed->counter['used_count'] ?? null, 'commit increments used count');
sr050_same(0, $committed->counter['reserved_count'] ?? null, 'commit releases reserved count');

$commitReplay = $service->commit($first->reservationId, 'req-1', '2026-06-29T10:02:00+00:00');
sr050_same(true, $commitReplay->ok, 'commit replay is idempotent');
sr050_same(1, $commitReplay->counter['used_count'] ?? null, 'commit replay does not double count');
sr050_same(0, $commitReplay->counter['reserved_count'] ?? null, 'commit replay does not make reserved negative');

$releaseStore = new InMemoryQuotaCounterStore();
$releaseService = new QuotaService($releaseStore);
$pending = sr050_reserve($releaseService, 502, 'req-release', limit: 1);
$released = $releaseService->release($pending->reservationId, 'req-release', '2026-06-29T10:03:00+00:00');
sr050_same(true, $released->ok, 'release succeeds for pending reservation');
sr050_same('released', $released->status, 'release status');
sr050_same(0, $released->counter['used_count'] ?? null, 'release does not increment used count');
sr050_same(0, $released->counter['reserved_count'] ?? null, 'release decrements reserved count');
$releaseReplay = $releaseService->release($pending->reservationId, 'req-release', '2026-06-29T10:04:00+00:00');
sr050_same(true, $releaseReplay->ok, 'release replay is idempotent');
sr050_same(0, $releaseReplay->counter['reserved_count'] ?? null, 'release replay does not make reserved negative');

$raceStore = new InMemoryQuotaCounterStore();
$raceService = new QuotaService($raceStore);
$successes = 0;
for ($i = 0; $i < 100; $i++) {
    $result = sr050_reserve($raceService, 600, 'race-'.$i, limit: 1);
    if ($result->ok) {
        $successes++;
    }
}
sr050_same(1, $successes, 'remaining one quota allows at most one of 100 concurrent reservations');
$raceCounter = $raceStore->counterSnapshot(600, 'month', '2026-06');
sr050_same(0, $raceCounter['used_count'], 'race reserve does not count used early');
sr050_same(1, $raceCounter['reserved_count'], 'race reserve never exceeds limit');

$sameRequestRaceStore = new Sr050InterleavingQuotaStore();
$sameRequestRaceService = new QuotaService($sameRequestRaceStore);
$sameRequestRaceStore->beforeLockedCallback = static function () use ($sameRequestRaceService): void {
    sr050_reserve($sameRequestRaceService, 610, 'same-request-race', limit: 2);
};
$sameRequestRace = sr050_reserve($sameRequestRaceService, 610, 'same-request-race', limit: 2);
sr050_same(true, $sameRequestRace->ok, 'same request race remains idempotent');
$sameRequestCounter = $sameRequestRaceStore->counterSnapshot(610, 'month', '2026-06');
sr050_same(1, $sameRequestCounter['reserved_count'] ?? null, 'same request race does not double reserve inside lock');

$sameCommitRaceStore = new Sr050InterleavingQuotaStore();
$sameCommitRaceService = new QuotaService($sameCommitRaceStore);
$sameCommitReservation = sr050_reserve($sameCommitRaceService, 611, 'same-commit-race', limit: 1);
$sameCommitRaceStore->beforeLockedCallback = static function () use ($sameCommitRaceService, $sameCommitReservation): void {
    $sameCommitRaceService->commit($sameCommitReservation->reservationId, 'same-commit-race', '2026-06-29T10:05:00+00:00');
};
$sameCommit = $sameCommitRaceService->commit(
    $sameCommitReservation->reservationId,
    'same-commit-race',
    '2026-06-29T10:06:00+00:00',
);
sr050_same(true, $sameCommit->ok, 'same commit race remains idempotent');
$sameCommitCounter = $sameCommitRaceStore->counterSnapshot(611, 'month', '2026-06');
sr050_same(1, $sameCommitCounter['used_count'] ?? null, 'same commit race does not double count used');
sr050_same(0, $sameCommitCounter['reserved_count'] ?? null, 'same commit race does not consume another reservation');

$periodService = new QuotaService(new InMemoryQuotaCounterStore());
$month = sr050_reserve($periodService, 700, 'period-month', limit: 1, periodType: 'month', periodKey: '2026-06');
$day = sr050_reserve($periodService, 700, 'period-day', limit: 1, periodType: 'day', periodKey: '2026-06-29');
sr050_same(true, $month->ok, 'month period reservation succeeds');
sr050_same(true, $day->ok, 'day period reservation uses a separate unique counter key');

$deadlockStore = new InMemoryQuotaCounterStore(deadlocksBeforeSuccess: 2);
$deadlockService = new QuotaService($deadlockStore, maxDeadlockRetries: 3);
$deadlock = sr050_reserve($deadlockService, 800, 'deadlock-ok', limit: 1);
sr050_same(true, $deadlock->ok, 'deadlock retries eventually reserve quota');
sr050_same(3, $deadlockStore->lockAttempts(), 'deadlock reserve used store lock retry loop');

$timeoutStore = new InMemoryQuotaCounterStore(lockTimeoutsBeforeSuccess: 1);
$timeoutService = new QuotaService($timeoutStore, maxDeadlockRetries: 0);
$timeout = sr050_reserve($timeoutService, 801, 'timeout-fail', limit: 1);
sr050_same(false, $timeout->ok, 'lock timeout fails closed');
sr050_same('lock_timeout', $timeout->status, 'lock timeout status');

echo "SR-050 quota service checks passed.\n";
