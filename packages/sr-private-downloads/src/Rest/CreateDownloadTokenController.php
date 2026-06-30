<?php
declare(strict_types=1);

namespace StockResource\PrivateDownloads\Rest;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use StockResource\Contracts\Entitlement\AccessDecisionContext;
use StockResource\Entitlements\Application\EntitlementService;
use StockResource\Entitlements\Application\QuotaService;
use StockResource\PrivateDownloads\Token\DownloadTokenIssueRequest;
use StockResource\PrivateDownloads\Token\DownloadTokenService;

final class CreateDownloadTokenController
{
    /**
     * @var list<string>
     */
    private array $events;

    public function __construct(
        private AccessDecisionGateway $access,
        private QuotaReservationGateway $quota,
        private DownloadTokenService $tokens,
        private CreateDownloadTokenIdempotencyStore $idempotency,
        private TransactionRunner $transactions,
    ) {
        $this->events = [];
    }

    public function create(CreateDownloadTokenRequest $request): CreateDownloadTokenResponse
    {
        $fingerprint = $request->fingerprint();
        return $this->transactions->run(function () use ($request, $fingerprint): CreateDownloadTokenResponse {
            $claim = $this->idempotency->claim($request->idempotencyKey, $fingerprint);
            if ($claim->status === 'conflict') {
                return CreateDownloadTokenResponse::error(409, 'idempotency_conflict');
            }
            if ($claim->status === 'replay' && $claim->record !== null) {
                return new CreateDownloadTokenResponse($claim->record->statusCode, $claim->record->responseBody);
            }
            if ($claim->status === 'in_progress') {
                return CreateDownloadTokenResponse::error(409, 'idempotency_in_progress');
            }

            $events = [];
            $decision = $this->access->decide($request);
            $events[] = 'decide';
            if (! $decision->allowed) {
                $this->events = $events;
                return $this->completeIdempotency(
                    request: $request,
                    fingerprint: $fingerprint,
                    response: CreateDownloadTokenResponse::error(403, $decision->reasonCode),
                );
            }

            $quotaReservationId = 'none';
            if ($decision->source === 'VIP') {
                $quotaResult = $this->quota->reserve($request, $decision);
                if (! $quotaResult->ok || $quotaResult->reservationId === null) {
                    $this->events = $events;
                    return $this->completeIdempotency(
                        request: $request,
                        fingerprint: $fingerprint,
                        response: CreateDownloadTokenResponse::error(409, $quotaResult->code),
                    );
                }
                $quotaReservationId = $quotaResult->reservationId;
                $events[] = 'reserve';
            }

            $issued = $this->tokens->issue(new DownloadTokenIssueRequest(
                requestId: $request->requestId,
                userId: $request->userId,
                resourceId: $request->resourceId,
                versionId: $request->versionId,
                entitlementId: $decision->entitlementId,
                quotaReservationId: $quotaReservationId,
                nowUtc: $request->nowUtc,
            ));
            $events[] = 'issue';

            $body = [
                'status' => 'created',
                'download_token' => $issued->rawToken,
                'token_id' => $issued->tokenId,
                'request_id' => $issued->requestId,
                'resource_id' => $issued->resourceId,
                'version_id' => $issued->versionId,
                'ttl_seconds' => $issued->ttlSeconds,
                'expires_at' => $issued->expiresAt,
            ];
            $this->idempotency->complete(new CreateDownloadTokenIdempotencyRecord($request->idempotencyKey, $fingerprint, $body, 200));
            $this->events = $events;

            return new CreateDownloadTokenResponse(201, $body);
        });
    }

    private function completeIdempotency(
        CreateDownloadTokenRequest $request,
        string $fingerprint,
        CreateDownloadTokenResponse $response,
    ): CreateDownloadTokenResponse {
        $this->idempotency->complete(new CreateDownloadTokenIdempotencyRecord(
            key: $request->idempotencyKey,
            fingerprint: $fingerprint,
            responseBody: $response->body,
            statusCode: $response->statusCode,
        ));

        return $response;
    }

    /**
     * @return list<string>
     */
    public function events(): array
    {
        return $this->events;
    }
}

final readonly class CreateDownloadTokenRequest
{
    public function __construct(
        public string $idempotencyKey,
        public string $requestId,
        public int $userId,
        public int $resourceId,
        public int $versionId,
        public string $accessMode,
        public string $resourceStatus,
        public string $source,
        public string $nowUtc,
        public array $taxonomyTermIds = [],
    ) {
        self::assertToken($this->idempotencyKey, 'idempotency_key');
        self::assertToken($this->requestId, 'request_id');
        self::assertPositive($this->userId, 'user_id');
        self::assertPositive($this->resourceId, 'resource_id');
        self::assertPositive($this->versionId, 'version_id');
        self::assertToken($this->accessMode, 'access_mode');
        self::assertToken($this->resourceStatus, 'resource_status');
        self::assertToken($this->source, 'source');
        if (DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $this->nowUtc) === false) {
            throw new \InvalidArgumentException('now_utc must be ISO-8601.');
        }
        foreach ($this->taxonomyTermIds as $termId) {
            if (! is_int($termId) || $termId < 1) {
                throw new \InvalidArgumentException('taxonomy_term_ids must contain positive integers.');
            }
        }
    }

    public function fingerprint(): string
    {
        try {
            return hash('sha256', json_encode([
                'request_id' => $this->requestId,
                'user_id' => $this->userId,
                'resource_id' => $this->resourceId,
                'version_id' => $this->versionId,
                'access_mode' => $this->accessMode,
                'resource_status' => $this->resourceStatus,
                'source' => $this->source,
                'taxonomy_term_ids' => $this->taxonomyTermIds,
            ], JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new \RuntimeException('Unable to fingerprint download token request.', 0, $exception);
        }
    }

    private static function assertPositive(int $value, string $field): void
    {
        if ($value < 1) {
            throw new \InvalidArgumentException($field.' must be positive.');
        }
    }

    private static function assertToken(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException($field.' is required.');
        }
    }
}

final readonly class CreateDownloadTokenResponse
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        public int $statusCode,
        public array $body,
    ) {
    }

    public static function error(int $statusCode, string $code): self
    {
        return new self($statusCode, [
            'status' => 'error',
            'code' => $code,
        ]);
    }
}

final readonly class AccessDecisionResult
{
    /**
     * @param array<string, mixed>|null $quota
     */
    public function __construct(
        public bool $allowed,
        public ?string $source,
        public ?int $entitlementId,
        public string $reasonCode,
        public ?array $quota = null,
    ) {
    }
}

interface AccessDecisionGateway
{
    public function decide(CreateDownloadTokenRequest $request): AccessDecisionResult;
}

interface QuotaReservationGateway
{
    public function reserve(CreateDownloadTokenRequest $request, AccessDecisionResult $decision): QuotaReservationResult;
}

final readonly class QuotaReservationResult
{
    public function __construct(
        public bool $ok,
        public string $code,
        public ?string $reservationId = null,
    ) {
    }

    public static function ok(string $reservationId): self
    {
        return new self(true, 'reserved', $reservationId);
    }

    public static function fail(string $code): self
    {
        return new self(false, $code);
    }
}

interface TransactionRunner
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}

interface CreateDownloadTokenIdempotencyStore
{
    public function claim(string $key, string $fingerprint): CreateDownloadTokenIdempotencyClaim;

    public function complete(CreateDownloadTokenIdempotencyRecord $record): void;
}

final readonly class CreateDownloadTokenIdempotencyClaim
{
    public function __construct(
        public string $status,
        public ?CreateDownloadTokenIdempotencyRecord $record = null,
    ) {
    }
}

final readonly class CreateDownloadTokenIdempotencyRecord
{
    /**
     * @param array<string, mixed> $responseBody
     */
    public function __construct(
        public string $key,
        public string $fingerprint,
        public array $responseBody,
        public int $statusCode = 200,
        public string $status = 'completed',
    ) {
    }

    public static function pending(string $key, string $fingerprint): self
    {
        return new self($key, $fingerprint, [], 409, 'pending');
    }
}

final class InMemoryCreateDownloadTokenIdempotencyStore implements CreateDownloadTokenIdempotencyStore
{
    /**
     * @var array<string, CreateDownloadTokenIdempotencyRecord>
     */
    private array $records = [];

    public function claim(string $key, string $fingerprint): CreateDownloadTokenIdempotencyClaim
    {
        $record = $this->records[$key] ?? null;
        if ($record === null) {
            $this->records[$key] = CreateDownloadTokenIdempotencyRecord::pending($key, $fingerprint);

            return new CreateDownloadTokenIdempotencyClaim('new');
        }
        if ($record->fingerprint !== $fingerprint) {
            return new CreateDownloadTokenIdempotencyClaim('conflict', $record);
        }
        if ($record->status === 'pending') {
            return new CreateDownloadTokenIdempotencyClaim('in_progress', $record);
        }

        return new CreateDownloadTokenIdempotencyClaim('replay', $record);
    }

    public function complete(CreateDownloadTokenIdempotencyRecord $record): void
    {
        $this->records[$record->key] = $record;
    }
}

final readonly class EntitlementServiceAccessDecisionGateway implements AccessDecisionGateway
{
    public function __construct(private EntitlementService $entitlements)
    {
    }

    public function decide(CreateDownloadTokenRequest $request): AccessDecisionResult
    {
        $decision = $this->entitlements->decide(new AccessDecisionContext(
            resourceId: $request->resourceId,
            userId: $request->userId,
            accessMode: $request->accessMode,
            resourceStatus: $request->resourceStatus,
            taxonomyTermIds: $request->taxonomyTermIds,
            atUtc: $request->nowUtc,
        ));

        return new AccessDecisionResult(
            allowed: $decision->allowed,
            source: $decision->source,
            entitlementId: $decision->entitlementId,
            reasonCode: $decision->reasonCode,
            quota: $decision->quota,
        );
    }
}

final readonly class QuotaServiceReservationGateway implements QuotaReservationGateway
{
    public function __construct(private QuotaService $quota)
    {
    }

    public function reserve(CreateDownloadTokenRequest $request, AccessDecisionResult $decision): QuotaReservationResult
    {
        if ($decision->entitlementId === null) {
            return QuotaReservationResult::fail('missing_entitlement');
        }

        $quota = is_array($decision->quota) ? $decision->quota : [];
        $result = $this->quota->reserve(
            entitlementId: $decision->entitlementId,
            userId: $request->userId,
            periodType: (string) ($quota['period_type'] ?? 'month'),
            periodKey: (string) ($quota['period_key'] ?? substr($request->nowUtc, 0, 7)),
            limit: (int) ($quota['limit'] ?? $quota['limit_snapshot'] ?? 1),
            requestId: $request->requestId,
            nowUtc: $request->nowUtc,
        );

        return $result->ok && $result->reservationId !== null
            ? QuotaReservationResult::ok($result->reservationId)
            : QuotaReservationResult::fail($result->status);
    }
}

final class RecordingTransactionRunner implements TransactionRunner
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

final class RecordingAccessDecisionGateway implements AccessDecisionGateway
{
    public int $calls = 0;

    public function __construct(
        private ?string $source,
        private ?int $entitlementId,
        private bool $allowed = true,
        private string $reason = 'allowed',
    ) {
    }

    public function decide(CreateDownloadTokenRequest $request): AccessDecisionResult
    {
        unset($request);
        $this->calls++;

        return new AccessDecisionResult($this->allowed, $this->source, $this->entitlementId, $this->reason, [
            'period_type' => 'month',
            'period_key' => '2026-06',
            'limit' => 10,
        ]);
    }
}

final class RecordingQuotaGateway implements QuotaReservationGateway
{
    public int $reserveCalls = 0;

    public function __construct(private string $reservationId)
    {
    }

    public function reserve(CreateDownloadTokenRequest $request, AccessDecisionResult $decision): QuotaReservationResult
    {
        unset($request, $decision);
        $this->reserveCalls++;

        return $this->reservationId === ''
            ? QuotaReservationResult::fail('quota_exhausted')
            : QuotaReservationResult::ok($this->reservationId);
    }
}
