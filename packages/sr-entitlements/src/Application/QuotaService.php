<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Application;

use RuntimeException;

final readonly class QuotaService
{
    public function __construct(
        private QuotaCounterStore $store,
        private int $maxDeadlockRetries = 3,
    ) {
    }

    public function reserve(
        int $entitlementId,
        int $userId,
        string $periodType,
        string $periodKey,
        int $limit,
        string $requestId,
        string $nowUtc,
    ): QuotaOperationResult {
        try {
            $this->assertPositive($entitlementId, 'entitlement_id');
            $this->assertPositive($userId, 'user_id');
            $this->assertPositive($limit, 'limit');
            $periodType = $this->normalizeToken($periodType, 'period_type');
            $periodKey = $this->normalizeToken($periodKey, 'period_key');
            $requestId = $this->normalizeToken($requestId, 'request_id');
            $this->assertUtc($nowUtc);

            $existing = $this->store->findReservationByRequestId($requestId);
            if ($existing !== null) {
                return $this->resultFromReservation($existing);
            }

            return $this->withDeadlockRetry(
                fn (QuotaService $service): QuotaOperationResult => $service->store->withCounterForUpdate(
                    entitlementId: $entitlementId,
                    userId: $userId,
                    periodType: $periodType,
                    periodKey: $periodKey,
                    limit: $limit,
                    nowUtc: $nowUtc,
                    callback: function (QuotaCounterRecord $counter) use (
                        $entitlementId,
                        $userId,
                        $periodType,
                        $periodKey,
                        $requestId,
                        $nowUtc,
                    ): QuotaOperationResult {
                        $existing = $this->store->findReservationByRequestId($requestId);
                        if ($existing !== null) {
                            return $this->resultFromReservation($existing);
                        }

                        $available = $counter->limitSnapshot - $counter->usedCount - $counter->reservedCount;
                        if ($available <= 0) {
                            return QuotaOperationResult::fail('quota_exhausted', null, $counter->toArray());
                        }

                        $reservation = QuotaReservationRecord::pending(
                            reservationId: self::reservationId($entitlementId, $periodType, $periodKey, $requestId),
                            requestId: $requestId,
                            entitlementId: $entitlementId,
                            userId: $userId,
                            periodType: $periodType,
                            periodKey: $periodKey,
                            createdAt: $nowUtc,
                            updatedAt: $nowUtc,
                        );

                        $counter->reservedCount++;
                        $counter->updatedAt = $nowUtc;
                        $counter->lockVersion++;
                        $reservation = $reservation->withStatus('reserved', $counter->toArray(), $nowUtc);
                        $this->store->saveReservation($reservation);

                        return QuotaOperationResult::ok('reserved', $reservation->reservationId, $counter->toArray());
                    },
                ),
            );
        } catch (QuotaStoreException $exception) {
            return QuotaOperationResult::fail($exception->codeName, null, []);
        }
    }

    public function commit(string $reservationId, string $requestId, string $nowUtc): QuotaOperationResult
    {
        return $this->complete($reservationId, $requestId, $nowUtc, 'committed');
    }

    public function release(string $reservationId, string $requestId, string $nowUtc): QuotaOperationResult
    {
        return $this->complete($reservationId, $requestId, $nowUtc, 'released');
    }

    private function complete(string $reservationId, string $requestId, string $nowUtc, string $targetStatus): QuotaOperationResult
    {
        try {
            $reservationId = $this->normalizeToken($reservationId, 'reservation_id');
            $requestId = $this->normalizeToken($requestId, 'request_id');
            $this->assertUtc($nowUtc);

            $reservation = $this->store->findReservation($reservationId);
            if ($reservation === null) {
                return QuotaOperationResult::fail('reservation_not_found');
            }

            if ($reservation->requestId !== $requestId) {
                return QuotaOperationResult::fail('reservation_request_mismatch');
            }

            if ($reservation->status === $targetStatus) {
                return $this->resultFromReservation($reservation);
            }

            if ($reservation->status !== 'reserved') {
                return QuotaOperationResult::fail('reservation_already_'.$reservation->status, $reservationId);
            }

            return $this->withDeadlockRetry(
                fn (QuotaService $service): QuotaOperationResult => $service->store->withCounterForUpdate(
                    entitlementId: $reservation->entitlementId,
                    userId: $reservation->userId,
                    periodType: $reservation->periodType,
                    periodKey: $reservation->periodKey,
                    limit: 1,
                    nowUtc: $nowUtc,
                    callback: function (QuotaCounterRecord $counter) use ($reservation, $targetStatus, $nowUtc): QuotaOperationResult {
                        $current = $this->store->findReservation($reservation->reservationId);
                        if ($current === null) {
                            return QuotaOperationResult::fail('reservation_not_found', $reservation->reservationId, $counter->toArray());
                        }

                        if ($current->requestId !== $reservation->requestId) {
                            return QuotaOperationResult::fail('reservation_request_mismatch', $reservation->reservationId, $counter->toArray());
                        }

                        if ($current->status === $targetStatus) {
                            return $this->resultFromReservation($current);
                        }

                        if ($current->status !== 'reserved') {
                            return QuotaOperationResult::fail('reservation_already_'.$current->status, $current->reservationId, $counter->toArray());
                        }

                        if ($counter->reservedCount < 1) {
                            return QuotaOperationResult::fail('counter_reserved_underflow', $reservation->reservationId, $counter->toArray());
                        }

                        $counter->reservedCount--;
                        if ($targetStatus === 'committed') {
                            $counter->usedCount++;
                        }
                        $counter->updatedAt = $nowUtc;
                        $counter->lockVersion++;

                        $updated = $current->withStatus($targetStatus, $counter->toArray(), $nowUtc);
                        $this->store->saveReservation($updated);

                        return QuotaOperationResult::ok($targetStatus, $updated->reservationId, $counter->toArray());
                    },
                ),
            );
        } catch (QuotaStoreException $exception) {
            return QuotaOperationResult::fail($exception->codeName, null, []);
        }
    }

    /**
     * @param callable(self): QuotaOperationResult $operation
     */
    private function withDeadlockRetry(callable $operation): QuotaOperationResult
    {
        $attempt = 0;
        while (true) {
            try {
                return $operation($this);
            } catch (QuotaStoreException $exception) {
                if ($exception->codeName !== 'deadlock_detected' || $attempt >= $this->maxDeadlockRetries) {
                    throw $exception;
                }
                $attempt++;
            }
        }
    }

    private function resultFromReservation(QuotaReservationRecord $reservation): QuotaOperationResult
    {
        return new QuotaOperationResult(
            ok: true,
            status: $reservation->status,
            reservationId: $reservation->reservationId,
            counter: $reservation->counterSnapshot,
        );
    }

    private function assertPositive(int $value, string $field): void
    {
        if ($value < 1) {
            throw QuotaStoreException::invalidArgument($field.' must be positive.');
        }
    }

    private function normalizeToken(string $value, string $field): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            throw QuotaStoreException::invalidArgument($field.' is required.');
        }

        return $normalized;
    }

    private function assertUtc(string $datetime): void
    {
        if (\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $datetime) === false) {
            throw QuotaStoreException::invalidArgument('datetime must be ISO-8601.');
        }
    }

    private static function reservationId(int $entitlementId, string $periodType, string $periodKey, string $requestId): string
    {
        return hash('sha256', $entitlementId.'|'.$periodType.'|'.$periodKey.'|'.$requestId);
    }
}

interface QuotaCounterStore
{
    /**
     * Implementations must lock the unique `(entitlement_id, period_type, period_key)` counter row
     * for update before executing the callback.
     *
     * @param callable(QuotaCounterRecord): QuotaOperationResult $callback
     */
    public function withCounterForUpdate(
        int $entitlementId,
        int $userId,
        string $periodType,
        string $periodKey,
        int $limit,
        string $nowUtc,
        callable $callback,
    ): QuotaOperationResult;

    public function findReservation(string $reservationId): ?QuotaReservationRecord;

    public function findReservationByRequestId(string $requestId): ?QuotaReservationRecord;

    public function saveReservation(QuotaReservationRecord $reservation): void;
}

final readonly class QuotaOperationResult
{
    /**
     * @param array<string, int|string> $counter
     */
    public function __construct(
        public bool $ok,
        public string $status,
        public ?string $reservationId = null,
        public array $counter = [],
    ) {
    }

    /**
     * @param array<string, int|string> $counter
     */
    public static function ok(string $status, string $reservationId, array $counter): self
    {
        return new self(true, $status, $reservationId, $counter);
    }

    /**
     * @param array<string, int|string> $counter
     */
    public static function fail(string $status, ?string $reservationId = null, array $counter = []): self
    {
        return new self(false, $status, $reservationId, $counter);
    }
}

final class QuotaCounterRecord
{
    public function __construct(
        public int $entitlementId,
        public int $userId,
        public string $periodType,
        public string $periodKey,
        public int $limitSnapshot,
        public int $usedCount,
        public int $reservedCount,
        public int $lockVersion,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'entitlement_id' => $this->entitlementId,
            'user_id' => $this->userId,
            'period_type' => $this->periodType,
            'period_key' => $this->periodKey,
            'limit_snapshot' => $this->limitSnapshot,
            'used_count' => $this->usedCount,
            'reserved_count' => $this->reservedCount,
            'lock_version' => $this->lockVersion,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

final readonly class QuotaReservationRecord
{
    /**
     * @param array<string, int|string> $counterSnapshot
     */
    public function __construct(
        public string $reservationId,
        public string $requestId,
        public int $entitlementId,
        public int $userId,
        public string $periodType,
        public string $periodKey,
        public string $status,
        public array $counterSnapshot,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function pending(
        string $reservationId,
        string $requestId,
        int $entitlementId,
        int $userId,
        string $periodType,
        string $periodKey,
        string $createdAt,
        string $updatedAt,
    ): self {
        return new self(
            reservationId: $reservationId,
            requestId: $requestId,
            entitlementId: $entitlementId,
            userId: $userId,
            periodType: $periodType,
            periodKey: $periodKey,
            status: 'reserved',
            counterSnapshot: [],
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    /**
     * @param array<string, int|string> $counterSnapshot
     */
    public function withStatus(string $status, array $counterSnapshot, string $updatedAt): self
    {
        return new self(
            reservationId: $this->reservationId,
            requestId: $this->requestId,
            entitlementId: $this->entitlementId,
            userId: $this->userId,
            periodType: $this->periodType,
            periodKey: $this->periodKey,
            status: $status,
            counterSnapshot: $counterSnapshot,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
        );
    }
}

final class InMemoryQuotaCounterStore implements QuotaCounterStore
{
    /**
     * @var array<string, QuotaCounterRecord>
     */
    private array $counters = [];

    /**
     * @var array<string, QuotaReservationRecord>
     */
    private array $reservations = [];

    /**
     * @var array<string, string>
     */
    private array $requestIndex = [];

    private int $lockAttempts = 0;

    public function __construct(
        private int $deadlocksBeforeSuccess = 0,
        private int $lockTimeoutsBeforeSuccess = 0,
    ) {
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
        $this->lockAttempts++;
        if ($this->deadlocksBeforeSuccess > 0) {
            $this->deadlocksBeforeSuccess--;
            throw QuotaStoreException::deadlock();
        }
        if ($this->lockTimeoutsBeforeSuccess > 0) {
            $this->lockTimeoutsBeforeSuccess--;
            throw QuotaStoreException::lockTimeout();
        }

        $key = self::counterKey($entitlementId, $periodType, $periodKey);
        if (! isset($this->counters[$key])) {
            $this->counters[$key] = new QuotaCounterRecord(
                entitlementId: $entitlementId,
                userId: $userId,
                periodType: $periodType,
                periodKey: $periodKey,
                limitSnapshot: $limit,
                usedCount: 0,
                reservedCount: 0,
                lockVersion: 0,
                createdAt: $nowUtc,
                updatedAt: $nowUtc,
            );
        }

        return $callback($this->counters[$key]);
    }

    public function findReservation(string $reservationId): ?QuotaReservationRecord
    {
        return $this->reservations[$reservationId] ?? null;
    }

    public function findReservationByRequestId(string $requestId): ?QuotaReservationRecord
    {
        $reservationId = $this->requestIndex[$requestId] ?? null;
        if ($reservationId === null) {
            return null;
        }

        return $this->findReservation($reservationId);
    }

    public function saveReservation(QuotaReservationRecord $reservation): void
    {
        $this->reservations[$reservation->reservationId] = $reservation;
        $this->requestIndex[$reservation->requestId] = $reservation->reservationId;
    }

    /**
     * @return array<string, int|string>
     */
    public function counterSnapshot(int $entitlementId, string $periodType, string $periodKey): array
    {
        return ($this->counters[self::counterKey($entitlementId, $periodType, $periodKey)] ?? null)?->toArray() ?? [];
    }

    public function lockAttempts(): int
    {
        return $this->lockAttempts;
    }

    private static function counterKey(int $entitlementId, string $periodType, string $periodKey): string
    {
        return $entitlementId.'|'.$periodType.'|'.$periodKey;
    }
}

final class QuotaStoreException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }

    public static function deadlock(): self
    {
        return new self('deadlock_detected', 'Deadlock detected while locking quota counter.');
    }

    public static function lockTimeout(): self
    {
        return new self('lock_timeout', 'Timed out while locking quota counter.');
    }

    public static function invalidArgument(string $message): self
    {
        return new self('invalid_quota_argument', $message);
    }
}
