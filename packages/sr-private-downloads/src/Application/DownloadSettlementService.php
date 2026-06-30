<?php
declare(strict_types=1);

namespace StockResource\PrivateDownloads\Application;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use StockResource\Entitlements\Application\QuotaService;

final readonly class DownloadSettlementService
{
    public function __construct(
        private DownloadSettlementRepository $repository,
        private SettlementQuotaGateway $quota,
        private SettlementNotifier $notifier,
        private SettlementClock $clock,
        private DownloadSettlementTransactionRunner $transactions,
    ) {
    }

    public function settleRedirected(string $requestId): DownloadSettlementResult
    {
        $requestId = $this->requestId($requestId);
        $token = $this->repository->tokenByRequestId($requestId);
        if ($token === null) {
            return $this->alertedFailure('token_not_found', $requestId);
        }

        if ($this->alreadySettled($token)) {
            return DownloadSettlementResult::ok('already_settled', $requestId);
        }

        if ($token->status !== 'consumed') {
            return $this->alertedFailure('token_not_consumed', $requestId);
        }

        return $this->transactions->run(fn (): DownloadSettlementResult => $this->repository->withTokenSettlementLock(
            $requestId,
            function (DownloadTokenSettlementRecord $locked) use ($requestId): DownloadSettlementResult {
                if ($this->alreadySettled($locked)) {
                    return DownloadSettlementResult::ok('already_settled', $requestId);
                }

                if ($locked->status !== 'consumed') {
                    return $this->alertedFailure('token_not_consumed', $requestId);
                }

                $quota = $this->quota->commit($locked->quotaReservationId, $requestId, $this->clock->nowUtc());
                if (! $quota->ok) {
                    return $this->alertedFailure($quota->status, $requestId);
                }

                $event = DownloadEventRecord::fromToken(
                    id: $this->repository->nextEventId(),
                    token: $locked,
                    result: 'redirected',
                    counted: true,
                    errorCode: null,
                    nowUtc: $this->clock->nowUtc(),
                );
                $this->repository->insertEventIfAbsent($event);

                return DownloadSettlementResult::ok('settled_redirected', $requestId, $event->id);
            },
        ));
    }

    public function settleFailed(string $requestId, string $failureCode): DownloadSettlementResult
    {
        return $this->releaseFailure($this->requestId($requestId), $failureCode, 'released_failed');
    }

    public function reconcile(DownloadReconcileRequest $request): DownloadSettlementResult
    {
        if ($this->repository->eventByRequestId($request->requestId) !== null) {
            return DownloadSettlementResult::ok('already_settled', $request->requestId);
        }

        $token = $this->repository->tokenByRequestId($request->requestId);
        if ($token === null) {
            return $this->alertedFailure('token_not_found', $request->requestId);
        }

        if ($token->status === 'consumed') {
            return $request->dryRun
                ? DownloadSettlementResult::ok('would_settle_redirected', $request->requestId)
                : $this->settleRedirected($request->requestId);
        }

        if ($token->status === 'failed') {
            return $request->dryRun
                ? DownloadSettlementResult::ok('would_release_failed', $request->requestId)
                : $this->settleFailed($request->requestId, $token->failureCode ?: 'delivery_failed');
        }

        if ($token->status === 'issued' && $this->isExpired($token->expiresAt)) {
            return $request->dryRun
                ? DownloadSettlementResult::ok('would_release_expired', $request->requestId)
                : $this->releaseExpired($token);
        }

        return DownloadSettlementResult::ok('no_action', $request->requestId);
    }

    private function releaseExpired(DownloadTokenSettlementRecord $token): DownloadSettlementResult
    {
        return $this->transactions->run(fn (): DownloadSettlementResult => $this->repository->withTokenSettlementLock(
            $token->requestId,
            function (DownloadTokenSettlementRecord $locked): DownloadSettlementResult {
                if ($this->alreadySettled($locked)) {
                    return DownloadSettlementResult::ok('already_settled', $locked->requestId);
                }

                if ($locked->status !== 'issued' && $locked->status !== 'expired') {
                    return $this->alertedFailure('token_not_expirable', $locked->requestId);
                }

                $quota = $this->quota->release($locked->quotaReservationId, $locked->requestId, $this->clock->nowUtc());
                if (! $quota->ok) {
                    return $this->alertedFailure($quota->status, $locked->requestId);
                }

                $updated = $locked->withStatus('expired', 'token_expired');
                $this->repository->saveToken($updated);
                $event = DownloadEventRecord::fromToken(
                    id: $this->repository->nextEventId(),
                    token: $updated,
                    result: 'failed',
                    counted: false,
                    errorCode: 'token_expired',
                    nowUtc: $this->clock->nowUtc(),
                );
                $this->repository->insertEventIfAbsent($event);

                return DownloadSettlementResult::ok('released_expired', $updated->requestId, $event->id);
            },
        ));
    }

    private function releaseFailure(string $requestId, string $failureCode, string $successStatus): DownloadSettlementResult
    {
        $token = $this->repository->tokenByRequestId($requestId);
        if ($token === null) {
            return $this->alertedFailure('token_not_found', $requestId);
        }

        if ($this->alreadySettled($token)) {
            return DownloadSettlementResult::ok('already_settled', $requestId);
        }

        $code = $this->failureCode($failureCode);
        return $this->transactions->run(fn (): DownloadSettlementResult => $this->repository->withTokenSettlementLock(
            $requestId,
            function (DownloadTokenSettlementRecord $locked) use ($requestId, $code, $successStatus): DownloadSettlementResult {
                if ($this->alreadySettled($locked)) {
                    return DownloadSettlementResult::ok('already_settled', $requestId);
                }

                if (! in_array($locked->status, ['failed', 'expired'], true)) {
                    return $this->alertedFailure('token_not_failed', $requestId);
                }

                $quota = $this->quota->release($locked->quotaReservationId, $requestId, $this->clock->nowUtc());
                if (! $quota->ok) {
                    return $this->alertedFailure($quota->status, $requestId);
                }

                $event = DownloadEventRecord::fromToken(
                    id: $this->repository->nextEventId(),
                    token: $locked,
                    result: 'failed',
                    counted: false,
                    errorCode: $code,
                    nowUtc: $this->clock->nowUtc(),
                );
                $this->repository->insertEventIfAbsent($event);

                return DownloadSettlementResult::ok($successStatus, $requestId, $event->id);
            },
        ));
    }

    private function alertedFailure(string $status, string $requestId): DownloadSettlementResult
    {
        $this->notifier->alert($status, $requestId);

        return DownloadSettlementResult::fail($status, $requestId);
    }

    private function alreadySettled(DownloadTokenSettlementRecord $token): bool
    {
        return $this->repository->eventByRequestId($token->requestId) !== null
            || $this->repository->eventByTokenId($token->tokenId) !== null;
    }

    private function isExpired(string $expiresAt): bool
    {
        return $this->datetime($this->clock->nowUtc()) >= $this->datetime($expiresAt);
    }

    private function requestId(string $requestId): string
    {
        $normalized = trim($requestId);
        if (! self::isUuid($normalized)) {
            throw new InvalidArgumentException('request_id must be a UUID.');
        }

        return $normalized;
    }

    private function failureCode(string $failureCode): string
    {
        $normalized = trim($failureCode);
        if (! (bool) preg_match('/^[a-z][a-z0-9_]{1,63}$/', $normalized)) {
            throw new InvalidArgumentException('failure_code is invalid.');
        }

        return $normalized;
    }

    private function datetime(string $value): DateTimeImmutable
    {
        $datetime = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
        if (! $datetime instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('datetime must be ISO-8601.');
        }

        return $datetime;
    }

    private static function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }
}

final readonly class DownloadReconcileRequest
{
    public string $requestId;

    public function __construct(
        string $requestId,
        public bool $dryRun = true,
    ) {
        $requestId = trim($requestId);
        if (! (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $requestId)) {
            throw new InvalidArgumentException('request_id must be a UUID.');
        }
        $this->requestId = $requestId;
    }
}

final readonly class DownloadSettlementResult
{
    public function __construct(
        public bool $ok,
        public string $status,
        public string $requestId,
        public ?int $eventId = null,
    ) {
    }

    public static function ok(string $status, string $requestId, ?int $eventId = null): self
    {
        return new self(true, $status, $requestId, $eventId);
    }

    public static function fail(string $status, string $requestId): self
    {
        return new self(false, $status, $requestId);
    }
}

final readonly class DownloadReconcileCommand
{
    public function __construct(private DownloadSettlementService $service)
    {
    }

    public static function commandName(): string
    {
        return 'sr downloads:reconcile';
    }

    /**
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     * @return array<string, mixed>
     */
    public function __invoke(array $args, array $assocArgs = []): array
    {
        $requestId = (string) ($assocArgs['request_id'] ?? $args[0] ?? '');
        $dryRun = array_key_exists('dry-run', $assocArgs)
            ? filter_var($assocArgs['dry-run'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false
            : true;

        $result = $this->service->reconcile(new DownloadReconcileRequest($requestId, $dryRun));

        return [
            'command' => self::commandName(),
            'ok' => $result->ok,
            'status' => $result->status,
            'request_id' => $result->requestId,
            'event_id' => $result->eventId,
            'dry_run' => $dryRun,
        ];
    }
}

final readonly class DownloadTokenSettlementRecord
{
    public function __construct(
        public int $tokenId,
        public string $requestId,
        public int $userId,
        public ?int $entitlementId,
        public int $resourceId,
        public int $versionId,
        public string $accessSource,
        public string $quotaReservationId,
        public string $status,
        public string $expiresAt,
        public ?string $redirectedAt = null,
        public ?string $failureCode = null,
    ) {
        foreach ([
            'token_id' => $this->tokenId,
            'user_id' => $this->userId,
            'resource_id' => $this->resourceId,
            'version_id' => $this->versionId,
        ] as $field => $value) {
            if ($value < 1) {
                throw new InvalidArgumentException($field.' must be positive.');
            }
        }
        if ($this->entitlementId !== null && $this->entitlementId < 1) {
            throw new InvalidArgumentException('entitlement_id must be positive.');
        }
        if (! (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->requestId)) {
            throw new InvalidArgumentException('request_id must be a UUID.');
        }
        if (trim($this->quotaReservationId) === '') {
            throw new InvalidArgumentException('quota_reservation_id is required.');
        }
        if (! in_array($this->status, ['issued', 'consumed', 'failed', 'expired'], true)) {
            throw new InvalidArgumentException('status is invalid.');
        }
        self::assertDate($this->expiresAt);
        if ($this->redirectedAt !== null) {
            self::assertDate($this->redirectedAt);
        }
        if (! (bool) preg_match('/^[a-z][a-z0-9_]{1,23}$/', $this->accessSource)) {
            throw new InvalidArgumentException('access_source is invalid.');
        }
    }

    public function withStatus(string $status, ?string $failureCode = null): self
    {
        return new self(
            tokenId: $this->tokenId,
            requestId: $this->requestId,
            userId: $this->userId,
            entitlementId: $this->entitlementId,
            resourceId: $this->resourceId,
            versionId: $this->versionId,
            accessSource: $this->accessSource,
            quotaReservationId: $this->quotaReservationId,
            status: $status,
            expiresAt: $this->expiresAt,
            redirectedAt: $this->redirectedAt,
            failureCode: $failureCode ?? $this->failureCode,
        );
    }

    private static function assertDate(string $value): void
    {
        if (DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value) === false) {
            throw new InvalidArgumentException('datetime must be ISO-8601.');
        }
    }
}

final readonly class DownloadEventRecord
{
    public function __construct(
        public int $id,
        public string $requestId,
        public int $tokenId,
        public int $userId,
        public ?int $entitlementId,
        public int $resourceId,
        public int $versionId,
        public string $accessSource,
        public bool $counted,
        public string $result,
        public ?string $errorCode,
        public string $startedAt,
        public ?string $redirectedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromToken(
        int $id,
        DownloadTokenSettlementRecord $token,
        string $result,
        bool $counted,
        ?string $errorCode,
        string $nowUtc,
    ): self {
        return new self(
            id: $id,
            requestId: $token->requestId,
            tokenId: $token->tokenId,
            userId: $token->userId,
            entitlementId: $token->entitlementId,
            resourceId: $token->resourceId,
            versionId: $token->versionId,
            accessSource: $token->accessSource,
            counted: $counted,
            result: $result,
            errorCode: $errorCode,
            startedAt: $nowUtc,
            redirectedAt: $result === 'redirected' ? ($token->redirectedAt ?? $nowUtc) : null,
            createdAt: $nowUtc,
            updatedAt: $nowUtc,
        );
    }
}

interface DownloadSettlementRepository
{
    public function tokenByRequestId(string $requestId): ?DownloadTokenSettlementRecord;

    public function saveToken(DownloadTokenSettlementRecord $record): void;

    /**
     * Implementations must lock the token row and guard the request_id/token_id event
     * uniqueness checks for the duration of the callback.
     *
     * @param callable(DownloadTokenSettlementRecord): DownloadSettlementResult $callback
     */
    public function withTokenSettlementLock(string $requestId, callable $callback): DownloadSettlementResult;

    public function eventByRequestId(string $requestId): ?DownloadEventRecord;

    public function eventByTokenId(int $tokenId): ?DownloadEventRecord;

    public function insertEventIfAbsent(DownloadEventRecord $event): DownloadEventRecord;

    public function nextEventId(): int;
}

interface DownloadSettlementTransactionRunner
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}

interface SettlementQuotaGateway
{
    public function commit(string $reservationId, string $requestId, string $nowUtc): SettlementQuotaResult;

    public function release(string $reservationId, string $requestId, string $nowUtc): SettlementQuotaResult;
}

final readonly class SettlementQuotaResult
{
    public function __construct(public bool $ok, public string $status)
    {
    }

    public static function ok(string $status): self
    {
        return new self(true, $status);
    }

    public static function fail(string $status): self
    {
        return new self(false, $status);
    }
}

interface SettlementNotifier
{
    public function alert(string $code, string $requestId): void;
}

interface SettlementClock
{
    public function nowUtc(): string;
}

final class InMemoryDownloadSettlementRepository implements DownloadSettlementRepository
{
    /**
     * @var array<string, DownloadTokenSettlementRecord>
     */
    private array $tokens = [];

    /**
     * @var array<string, DownloadEventRecord>
     */
    public array $events = [];

    private int $nextEventId = 1;

    public function tokenByRequestId(string $requestId): ?DownloadTokenSettlementRecord
    {
        return $this->tokens[$requestId] ?? null;
    }

    public function saveToken(DownloadTokenSettlementRecord $record): void
    {
        $this->tokens[$record->requestId] = $record;
    }

    public function withTokenSettlementLock(string $requestId, callable $callback): DownloadSettlementResult
    {
        $token = $this->tokenByRequestId($requestId);
        if ($token === null) {
            return DownloadSettlementResult::fail('token_not_found', $requestId);
        }

        return $callback($token);
    }

    public function eventByRequestId(string $requestId): ?DownloadEventRecord
    {
        return $this->events[$requestId] ?? null;
    }

    public function eventByTokenId(int $tokenId): ?DownloadEventRecord
    {
        foreach ($this->events as $event) {
            if ($event->tokenId === $tokenId) {
                return $event;
            }
        }

        return null;
    }

    public function insertEventIfAbsent(DownloadEventRecord $event): DownloadEventRecord
    {
        if (isset($this->events[$event->requestId])) {
            return $this->events[$event->requestId];
        }

        foreach ($this->events as $existing) {
            if ($existing->tokenId === $event->tokenId) {
                return $existing;
            }
        }

        $this->events[$event->requestId] = $event;

        return $event;
    }

    public function nextEventId(): int
    {
        return $this->nextEventId++;
    }
}

final class RecordingDownloadSettlementTransactionRunner implements DownloadSettlementTransactionRunner
{
    /**
     * @var list<string>
     */
    public array $events = [];

    public function run(callable $callback): mixed
    {
        $this->events[] = 'begin';
        try {
            $result = $callback();
            $this->events[] = 'commit';

            return $result;
        } catch (\Throwable $throwable) {
            $this->events[] = 'rollback';
            throw $throwable;
        }
    }
}

final readonly class QuotaServiceSettlementGateway implements SettlementQuotaGateway
{
    public function __construct(private QuotaService $quota)
    {
    }

    public function commit(string $reservationId, string $requestId, string $nowUtc): SettlementQuotaResult
    {
        $result = $this->quota->commit($reservationId, $requestId, $nowUtc);

        return $result->ok ? SettlementQuotaResult::ok($result->status) : SettlementQuotaResult::fail($result->status);
    }

    public function release(string $reservationId, string $requestId, string $nowUtc): SettlementQuotaResult
    {
        $result = $this->quota->release($reservationId, $requestId, $nowUtc);

        return $result->ok ? SettlementQuotaResult::ok($result->status) : SettlementQuotaResult::fail($result->status);
    }
}

final class RecordingSettlementQuotaGateway implements SettlementQuotaGateway
{
    /**
     * @var list<string>
     */
    public array $events = [];

    public function __construct(private string $failStatus = '')
    {
    }

    public function commit(string $reservationId, string $requestId, string $nowUtc): SettlementQuotaResult
    {
        unset($nowUtc);
        if ($this->failStatus !== '') {
            return SettlementQuotaResult::fail($this->failStatus);
        }

        $this->events[] = 'commit:'.$reservationId.':'.$requestId;

        return SettlementQuotaResult::ok('committed');
    }

    public function release(string $reservationId, string $requestId, string $nowUtc): SettlementQuotaResult
    {
        unset($nowUtc);
        if ($this->failStatus !== '') {
            return SettlementQuotaResult::fail($this->failStatus);
        }

        $this->events[] = 'release:'.$reservationId.':'.$requestId;

        return SettlementQuotaResult::ok('released');
    }
}

final class RecordingSettlementNotifier implements SettlementNotifier
{
    /**
     * @var list<string>
     */
    public array $events = [];

    public function alert(string $code, string $requestId): void
    {
        $this->events[] = 'alert:'.$code.':'.$requestId;
    }
}

final readonly class RecordingSettlementClock implements SettlementClock
{
    public function __construct(private string $nowUtc)
    {
        if (DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $this->nowUtc) === false) {
            throw new InvalidArgumentException('now_utc must be ISO-8601.');
        }
    }

    public function nowUtc(): string
    {
        return $this->nowUtc;
    }
}
