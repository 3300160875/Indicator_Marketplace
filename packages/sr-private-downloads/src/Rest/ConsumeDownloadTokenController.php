<?php
declare(strict_types=1);

namespace StockResource\PrivateDownloads\Rest;

use DateTimeImmutable;
use DateTimeInterface;
use StockResource\PrivateDownloads\Security\DownloadSecurityPolicy;
use StockResource\PrivateDownloads\Security\DownloadSecurityRequest;

final class ConsumeDownloadTokenController
{
    private DeliveryTransactionRunner $transactions;

    public function __construct(
        private TokenConsumptionGateway $tokens,
        private SignedUrlGateway $signer,
        private DeliveryQuotaGateway $quota,
        private DeliveryEventSink $events,
        ?DeliveryTransactionRunner $transactions = null,
        private ?DeliverySecurityGateway $security = null,
    ) {
        $this->transactions = $transactions ?? new RecordingDeliveryTransactionRunner();
    }

    public function consume(ConsumeDownloadTokenRequest $request): ConsumeDownloadTokenResponse
    {
        $security = $this->security?->inspect($request);
        if ($security !== null && ! $security->allowed) {
            return ConsumeDownloadTokenResponse::securityError(
                statusCode: $security->statusCode,
                code: $security->code,
                requestId: $request->requestId(),
                retryAfterUtc: $security->retryAfterUtc,
            );
        }

        $locked = $this->tokens->lockForDelivery($request);
        if ($locked->record === null) {
            return ConsumeDownloadTokenResponse::error($locked->statusCode, $locked->code, $request->requestId());
        }

        $signed = $this->signer->sign($locked->record, maxTtlSeconds: 60);
        if (! $signed->ok || $signed->url === null) {
            $this->transactions->run(function () use ($locked, $signed): void {
                $failed = $this->tokens->markFailed($locked->record, $signed->code);
                $this->quota->release($failed->quotaReservationId);
                $this->events->failed($failed, $signed->code);
            });

            return ConsumeDownloadTokenResponse::error($signed->statusCode, $signed->code, $request->requestId());
        }

        $consumed = $this->transactions->run(function () use ($locked): ?TokenDeliveryRecord {
            $consumed = $this->tokens->consumeForDelivery($locked->record);
            if ($consumed === null) {
                return null;
            }

            $this->quota->commit($consumed->quotaReservationId);
            $this->events->redirected($consumed);

            return $consumed;
        });
        if ($consumed === null) {
            return ConsumeDownloadTokenResponse::error(410, 'token_already_used', $request->requestId());
        }

        return new ConsumeDownloadTokenResponse(
            statusCode: 302,
            headers: [
                'Location' => $signed->url,
                'Cache-Control' => 'private, no-store',
                'X-Request-ID' => $request->requestId(),
            ],
            body: [
                'status' => 'redirected',
                'ttl_seconds' => $signed->ttlSeconds,
            ],
        );
    }
}

final readonly class ConsumeDownloadTokenRequest
{
    public function __construct(
        public string $rawToken,
        public int $userId,
        public int $resourceId,
        public int $versionId,
        public string $nowUtc,
        public string $requestId = '',
        public ?string $ipHash = null,
        public ?string $uaHash = null,
    ) {
        if (trim($this->rawToken) === '') {
            throw new \InvalidArgumentException('raw_token is required.');
        }
        foreach (['user_id' => $this->userId, 'resource_id' => $this->resourceId, 'version_id' => $this->versionId] as $field => $value) {
            if ($value < 1) {
                throw new \InvalidArgumentException($field.' must be positive.');
            }
        }
        if (DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $this->nowUtc) === false) {
            throw new \InvalidArgumentException('now_utc must be ISO-8601.');
        }
        foreach (['ip_hash' => $this->ipHash, 'ua_hash' => $this->uaHash] as $field => $value) {
            if ($value !== null && ! (bool) preg_match('/^[a-f0-9]{64}$/i', $value)) {
                throw new \InvalidArgumentException($field.' must be a sha256 hex hash.');
            }
        }
    }

    public function requestId(): string
    {
        $normalized = trim($this->requestId);

        if ($normalized !== '') {
            if (! self::isUuid($normalized)) {
                throw new \InvalidArgumentException('request_id must be a UUID.');
            }

            return $normalized;
        }

        return self::uuidFromSeed($this->rawToken.'|'.$this->userId.'|'.$this->resourceId.'|'.$this->versionId.'|'.$this->nowUtc);
    }

    private static function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }

    private static function uuidFromSeed(string $seed): string
    {
        $hash = md5($seed);

        return substr($hash, 0, 8).'-'
            .substr($hash, 8, 4).'-4'
            .substr($hash, 13, 3).'-a'
            .substr($hash, 17, 3).'-'
            .substr($hash, 20, 12);
    }
}

final readonly class ConsumeDownloadTokenResponse
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $body
     */
    public function __construct(
        public int $statusCode,
        public array $headers,
        public array $body,
    ) {
    }

    public static function error(int $statusCode, string $code, string $requestId): self
    {
        return new self($statusCode, [
            'Cache-Control' => 'private, no-store',
            'X-Request-ID' => $requestId,
        ], [
            'error_code' => $code,
            'message' => self::messageFor($code),
            'request_id' => $requestId,
        ]);
    }

    public static function securityError(int $statusCode, string $code, string $requestId, ?string $retryAfterUtc = null): self
    {
        $body = [
            'error_code' => $code,
            'message' => self::messageFor($code),
            'request_id' => $requestId,
        ];
        if ($retryAfterUtc !== null) {
            $body['retry_after_utc'] = $retryAfterUtc;
        }

        return new self($statusCode, [
            'Cache-Control' => 'private, no-store',
            'X-Request-ID' => $requestId,
        ], $body);
    }

    private static function messageFor(string $code): string
    {
        return match ($code) {
            'token_not_found' => 'Download token was not found.',
            'token_already_used' => 'Download token has already been used.',
            'token_binding_mismatch' => 'Download token does not match this request.',
            'token_expired' => 'Download token has expired.',
            'object_not_found' => 'Download object was not found.',
            'token_replay' => 'Download token replay was blocked.',
            'rate_limited_user', 'rate_limited_ip', 'rate_limited_resource' => 'Download request rate limit exceeded.',
            'account_sharing_restricted' => 'Download access is temporarily restricted.',
            default => 'Download delivery failed.',
        };
    }
}

final readonly class TokenDeliveryRecord
{
    public function __construct(
        public int $tokenId,
        public string $requestId,
        public int $userId,
        public int $resourceId,
        public int $versionId,
        public string $quotaReservationId,
        public string $storageKey,
        public string $status,
        public string $expiresAt,
    ) {
    }

    public function withStatus(string $status): self
    {
        return new self(
            tokenId: $this->tokenId,
            requestId: $this->requestId,
            userId: $this->userId,
            resourceId: $this->resourceId,
            versionId: $this->versionId,
            quotaReservationId: $this->quotaReservationId,
            storageKey: $this->storageKey,
            status: $status,
            expiresAt: $this->expiresAt,
        );
    }
}

final readonly class TokenLockResult
{
    public function __construct(
        public ?TokenDeliveryRecord $record,
        public string $code,
        public int $statusCode,
    ) {
    }

    public static function ok(TokenDeliveryRecord $record): self
    {
        return new self($record, 'ok', 200);
    }

    public static function fail(string $code, int $statusCode): self
    {
        return new self(null, $code, $statusCode);
    }
}

final readonly class SignedUrlResult
{
    public function __construct(
        public bool $ok,
        public string $code,
        public ?string $url = null,
        public int $ttlSeconds = 0,
        public int $statusCode = 500,
    ) {
    }

    public static function ok(string $url, int $ttlSeconds): self
    {
        if ($ttlSeconds < 1 || $ttlSeconds > 120) {
            return self::fail('signed_url_ttl_invalid', 503);
        }

        return new self(true, 'signed', $url, $ttlSeconds, 302);
    }

    public static function fail(string $code, int $statusCode): self
    {
        return new self(false, $code, null, 0, $statusCode);
    }
}

interface TokenConsumptionGateway
{
    public function lockForDelivery(ConsumeDownloadTokenRequest $request): TokenLockResult;

    public function consumeForDelivery(TokenDeliveryRecord $record): ?TokenDeliveryRecord;

    public function markFailed(TokenDeliveryRecord $record, string $reasonCode): TokenDeliveryRecord;
}

interface SignedUrlGateway
{
    public function sign(TokenDeliveryRecord $record, int $maxTtlSeconds): SignedUrlResult;
}

interface DeliveryQuotaGateway
{
    public function commit(string $quotaReservationId): void;

    public function release(string $quotaReservationId): void;
}

interface DeliverySecurityGateway
{
    public function inspect(ConsumeDownloadTokenRequest $request): DeliverySecurityDecision;
}

final readonly class DeliverySecurityDecision
{
    public function __construct(
        public bool $allowed,
        public string $code,
        public int $statusCode,
        public ?string $retryAfterUtc = null,
    ) {
    }

    public static function allow(): self
    {
        return new self(true, 'allowed', 200);
    }

    public static function block(string $code, int $statusCode, ?string $retryAfterUtc = null): self
    {
        return new self(false, $code, $statusCode, $retryAfterUtc);
    }
}

final readonly class DownloadSecurityPolicyGateway implements DeliverySecurityGateway
{
    public function __construct(private DownloadSecurityPolicy $policy)
    {
    }

    public function inspect(ConsumeDownloadTokenRequest $request): DeliverySecurityDecision
    {
        $decision = $this->policy->inspect(new DownloadSecurityRequest(
            requestId: $request->requestId(),
            userId: $request->userId,
            resourceId: $request->resourceId,
            versionId: $request->versionId,
            tokenFingerprint: hash('sha256', $request->rawToken),
            ipHash: $request->ipHash ?? hash('sha256', 'missing-ip'),
            uaHash: $request->uaHash ?? hash('sha256', 'missing-ua'),
            nowUtc: $request->nowUtc,
        ));
        if ($decision->allowed) {
            return DeliverySecurityDecision::allow();
        }

        return DeliverySecurityDecision::block(
            code: $decision->code,
            statusCode: self::statusCodeFor($decision->code),
            retryAfterUtc: $decision->retryAfterUtc,
        );
    }

    private static function statusCodeFor(string $code): int
    {
        return match ($code) {
            'token_replay' => 410,
            'account_sharing_restricted' => 429,
            default => str_starts_with($code, 'rate_limited_') ? 429 : 403,
        };
    }
}

interface DeliveryEventSink
{
    public function redirected(TokenDeliveryRecord $record): void;

    public function failed(TokenDeliveryRecord $record, string $reasonCode): void;
}

interface DeliveryTransactionRunner
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}

final class RecordingTokenConsumptionGateway implements TokenConsumptionGateway
{
    /**
     * @param array<string, TokenDeliveryRecord> $records
     */
    public function __construct(public array $records)
    {
    }

    public function lockForDelivery(ConsumeDownloadTokenRequest $request): TokenLockResult
    {
        $record = $this->records[$request->rawToken] ?? null;
        if ($record === null) {
            return TokenLockResult::fail('token_not_found', 410);
        }
        if ($record->status !== 'issued') {
            return TokenLockResult::fail('token_already_used', 410);
        }
        if ($record->userId !== $request->userId || $record->resourceId !== $request->resourceId || $record->versionId !== $request->versionId) {
            return TokenLockResult::fail('token_binding_mismatch', 403);
        }
        if (DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $request->nowUtc) >= DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $record->expiresAt)) {
            return TokenLockResult::fail('token_expired', 410);
        }

        return TokenLockResult::ok($record);
    }

    public function consumeForDelivery(TokenDeliveryRecord $record): ?TokenDeliveryRecord
    {
        $current = $this->findByTokenId($record->tokenId);
        if ($current === null || $current->status !== 'issued') {
            return null;
        }

        $updated = $current->withStatus('consumed');
        $this->replace($updated);

        return $updated;
    }

    public function markFailed(TokenDeliveryRecord $record, string $reasonCode): TokenDeliveryRecord
    {
        unset($reasonCode);
        $updated = $record->withStatus('failed');
        $this->replace($updated);

        return $updated;
    }

    private function replace(TokenDeliveryRecord $record): void
    {
        foreach ($this->records as $rawToken => $candidate) {
            if ($candidate->tokenId === $record->tokenId) {
                $this->records[$rawToken] = $record;
                return;
            }
        }
    }

    private function findByTokenId(int $tokenId): ?TokenDeliveryRecord
    {
        foreach ($this->records as $candidate) {
            if ($candidate->tokenId === $tokenId) {
                return $candidate;
            }
        }

        return null;
    }
}

final readonly class RecordingSignedUrlGateway implements SignedUrlGateway
{
    public function __construct(
        private string $url,
        private int $ttlSeconds = 60,
        private string $failCode = '',
    ) {
    }

    public function sign(TokenDeliveryRecord $record, int $maxTtlSeconds): SignedUrlResult
    {
        if (trim($record->storageKey) === '') {
            return SignedUrlResult::fail('object_not_found', 503);
        }
        if ($this->failCode !== '') {
            return SignedUrlResult::fail($this->failCode, 503);
        }
        if ($this->ttlSeconds > $maxTtlSeconds) {
            return SignedUrlResult::fail('signed_url_ttl_invalid', 503);
        }

        return SignedUrlResult::ok($this->url, $this->ttlSeconds);
    }
}

final class RecordingDeliveryQuotaGateway implements DeliveryQuotaGateway
{
    /**
     * @var list<string>
     */
    public array $events = [];

    public function commit(string $quotaReservationId): void
    {
        $this->events[] = 'commit:'.$quotaReservationId;
    }

    public function release(string $quotaReservationId): void
    {
        $this->events[] = 'release:'.$quotaReservationId;
    }
}

final class RecordingDeliveryEventSink implements DeliveryEventSink
{
    /**
     * @var list<string>
     */
    public array $events = [];

    public function redirected(TokenDeliveryRecord $record): void
    {
        $this->events[] = 'redirected:'.$record->tokenId;
    }

    public function failed(TokenDeliveryRecord $record, string $reasonCode): void
    {
        $this->events[] = 'failed:'.$record->tokenId.':'.$reasonCode;
    }
}

final class RecordingDeliveryTransactionRunner implements DeliveryTransactionRunner
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
