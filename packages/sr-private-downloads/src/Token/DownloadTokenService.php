<?php
declare(strict_types=1);

namespace StockResource\PrivateDownloads\Token;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;

final readonly class DownloadTokenSchema
{
    public function tableName(string $prefix = ''): string
    {
        return $prefix.'sr_download_tokens';
    }

    public function sql(string $prefix = '{prefix}'): string
    {
        $table = $this->tableName($prefix);

        return <<<SQL
CREATE TABLE {$table} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id VARCHAR(128) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  resource_id BIGINT UNSIGNED NOT NULL,
  version_id BIGINT UNSIGNED NOT NULL,
  entitlement_id BIGINT UNSIGNED NOT NULL,
  quota_reservation_id VARCHAR(128) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'issued',
  issued_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_download_token_request (request_id),
  UNIQUE KEY uq_download_token_hash (token_hash),
  KEY idx_download_token_user_resource (user_id, resource_id, version_id),
  KEY idx_download_token_expiry (status, expires_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    }
}

final readonly class DownloadTokenIssueRequest
{
    public function __construct(
        public string $requestId,
        public int $userId,
        public int $resourceId,
        public int $versionId,
        public int $entitlementId,
        public string $quotaReservationId,
        public string $nowUtc,
        public int $ttlSeconds = 120,
    ) {
        self::assertToken($this->requestId, 'request_id');
        self::assertPositive($this->userId, 'user_id');
        self::assertPositive($this->resourceId, 'resource_id');
        self::assertPositive($this->versionId, 'version_id');
        self::assertPositive($this->entitlementId, 'entitlement_id');
        self::assertToken($this->quotaReservationId, 'quota_reservation_id');
        self::assertUtc($this->nowUtc, 'now_utc');
        if ($this->ttlSeconds < 1 || $this->ttlSeconds > 3600) {
            throw DownloadTokenException::invalidArgument('ttl_seconds must be between 1 and 3600.');
        }
    }

    private static function assertPositive(int $value, string $field): void
    {
        if ($value < 1) {
            throw DownloadTokenException::invalidArgument($field.' must be positive.');
        }
    }

    private static function assertToken(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw DownloadTokenException::invalidArgument($field.' is required.');
        }
    }

    private static function assertUtc(string $value, string $field): void
    {
        if (DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value) === false) {
            throw DownloadTokenException::invalidArgument($field.' must be ISO-8601.');
        }
    }
}

final readonly class DownloadTokenIssueResult
{
    public function __construct(
        public string $rawToken,
        public int $tokenId,
        public string $requestId,
        public int $userId,
        public int $resourceId,
        public int $versionId,
        public int $entitlementId,
        public string $quotaReservationId,
        public int $ttlSeconds,
        public string $expiresAt,
    ) {
    }

    /**
     * @return array<string, int|string>
     */
    public function safeContext(): array
    {
        return [
            'token_id' => $this->tokenId,
            'request_id' => $this->requestId,
            'user_id' => $this->userId,
            'resource_id' => $this->resourceId,
            'version_id' => $this->versionId,
            'entitlement_id' => $this->entitlementId,
            'quota_reservation_id' => $this->quotaReservationId,
            'ttl_seconds' => $this->ttlSeconds,
            'expires_at' => $this->expiresAt,
        ];
    }
}

final readonly class DownloadTokenConsumeResult
{
    public function __construct(
        public bool $ok,
        public string $status,
        public ?int $tokenId = null,
        public ?string $requestId = null,
        public ?int $userId = null,
        public ?int $resourceId = null,
        public ?int $versionId = null,
        public ?int $entitlementId = null,
        public ?string $quotaReservationId = null,
    ) {
    }

    public static function fail(string $status): self
    {
        return new self(ok: false, status: $status);
    }

    public static function consumed(DownloadTokenRecord $record): self
    {
        return new self(
            ok: true,
            status: 'consumed',
            tokenId: $record->id,
            requestId: $record->requestId,
            userId: $record->userId,
            resourceId: $record->resourceId,
            versionId: $record->versionId,
            entitlementId: $record->entitlementId,
            quotaReservationId: $record->quotaReservationId,
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function safeContext(): array
    {
        return [
            'ok' => $this->ok ? 1 : 0,
            'status' => $this->status,
            'token_id' => $this->tokenId,
            'request_id' => $this->requestId,
            'user_id' => $this->userId,
            'resource_id' => $this->resourceId,
            'version_id' => $this->versionId,
            'entitlement_id' => $this->entitlementId,
            'quota_reservation_id' => $this->quotaReservationId,
        ];
    }
}

final readonly class DownloadTokenRecord
{
    public function __construct(
        public int $id,
        public string $requestId,
        public string $tokenHash,
        public int $userId,
        public int $resourceId,
        public int $versionId,
        public int $entitlementId,
        public string $quotaReservationId,
        public string $status,
        public string $issuedAt,
        public string $expiresAt,
        public ?string $usedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function issued(
        string $requestId,
        string $tokenHash,
        int $userId,
        int $resourceId,
        int $versionId,
        int $entitlementId,
        string $quotaReservationId,
        string $issuedAt,
        string $expiresAt,
    ): self {
        return new self(
            id: 0,
            requestId: $requestId,
            tokenHash: $tokenHash,
            userId: $userId,
            resourceId: $resourceId,
            versionId: $versionId,
            entitlementId: $entitlementId,
            quotaReservationId: $quotaReservationId,
            status: 'issued',
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            usedAt: null,
            createdAt: $issuedAt,
            updatedAt: $issuedAt,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            id: $id,
            requestId: $this->requestId,
            tokenHash: $this->tokenHash,
            userId: $this->userId,
            resourceId: $this->resourceId,
            versionId: $this->versionId,
            entitlementId: $this->entitlementId,
            quotaReservationId: $this->quotaReservationId,
            status: $this->status,
            issuedAt: $this->issuedAt,
            expiresAt: $this->expiresAt,
            usedAt: $this->usedAt,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }

    public function markConsumed(string $usedAt): self
    {
        return new self(
            id: $this->id,
            requestId: $this->requestId,
            tokenHash: $this->tokenHash,
            userId: $this->userId,
            resourceId: $this->resourceId,
            versionId: $this->versionId,
            entitlementId: $this->entitlementId,
            quotaReservationId: $this->quotaReservationId,
            status: 'consumed',
            issuedAt: $this->issuedAt,
            expiresAt: $this->expiresAt,
            usedAt: $usedAt,
            createdAt: $this->createdAt,
            updatedAt: $usedAt,
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toStorageArray(): array
    {
        return [
            'id' => $this->id,
            'request_id' => $this->requestId,
            'token_hash' => $this->tokenHash,
            'user_id' => $this->userId,
            'resource_id' => $this->resourceId,
            'version_id' => $this->versionId,
            'entitlement_id' => $this->entitlementId,
            'quota_reservation_id' => $this->quotaReservationId,
            'status' => $this->status,
            'issued_at' => $this->issuedAt,
            'expires_at' => $this->expiresAt,
            'used_at' => $this->usedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

interface DownloadTokenRepository
{
    public function create(DownloadTokenRecord $record): DownloadTokenRecord;

    public function save(DownloadTokenRecord $record): DownloadTokenRecord;

    public function findByRequestId(string $requestId): ?DownloadTokenRecord;

    public function findByTokenHash(string $tokenHash): ?DownloadTokenRecord;

    public function consumeIssuedToken(
        string $tokenHash,
        int $userId,
        int $resourceId,
        int $versionId,
        string $nowUtc,
    ): ?DownloadTokenRecord;
}

class InMemoryDownloadTokenRepository implements DownloadTokenRepository
{
    /**
     * @var array<int, DownloadTokenRecord>
     */
    private array $records = [];

    /**
     * @var array<string, int>
     */
    private array $requestIndex = [];

    /**
     * @var array<string, int>
     */
    private array $hashIndex = [];

    public function create(DownloadTokenRecord $record): DownloadTokenRecord
    {
        if (isset($this->requestIndex[$record->requestId])) {
            throw DownloadTokenException::duplicateRequestId();
        }
        if (isset($this->hashIndex[$record->tokenHash])) {
            throw DownloadTokenException::duplicateTokenHash();
        }

        $id = count($this->records) === 0 ? 1 : max(array_keys($this->records)) + 1;
        $stored = $record->withId($id);
        $this->records[$id] = $stored;
        $this->requestIndex[$stored->requestId] = $id;
        $this->hashIndex[$stored->tokenHash] = $id;

        return $stored;
    }

    public function save(DownloadTokenRecord $record): DownloadTokenRecord
    {
        if ($record->id < 1 || ! isset($this->records[$record->id])) {
            throw DownloadTokenException::notFound();
        }

        $current = $this->records[$record->id];
        if ($current->requestId !== $record->requestId || $current->tokenHash !== $record->tokenHash) {
            throw DownloadTokenException::immutableIdentity();
        }

        $this->records[$record->id] = $record;

        return $record;
    }

    public function findByRequestId(string $requestId): ?DownloadTokenRecord
    {
        $id = $this->requestIndex[$requestId] ?? null;

        return $id === null ? null : ($this->records[$id] ?? null);
    }

    public function findByTokenHash(string $tokenHash): ?DownloadTokenRecord
    {
        $id = $this->hashIndex[$tokenHash] ?? null;

        return $id === null ? null : ($this->records[$id] ?? null);
    }

    public function consumeIssuedToken(
        string $tokenHash,
        int $userId,
        int $resourceId,
        int $versionId,
        string $nowUtc,
    ): ?DownloadTokenRecord {
        $id = $this->hashIndex[$tokenHash] ?? null;
        if ($id === null || ! isset($this->records[$id])) {
            return null;
        }

        $record = $this->records[$id];
        if (
            $record->status !== 'issued'
            || $record->usedAt !== null
            || $record->userId !== $userId
            || $record->resourceId !== $resourceId
            || $record->versionId !== $versionId
            || self::parseUtc($nowUtc) >= self::parseUtc($record->expiresAt)
        ) {
            return null;
        }

        $consumed = $record->markConsumed($nowUtc);
        $this->records[$id] = $consumed;

        return $consumed;
    }

    private static function parseUtc(string $datetime): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $datetime);
        if ($parsed === false) {
            throw DownloadTokenException::invalidArgument('datetime must be ISO-8601.');
        }

        return $parsed->setTimezone(new DateTimeZone('UTC'));
    }
}

interface TokenBytes
{
    public function bytes(int $length): string;
}

final readonly class RandomTokenBytes implements TokenBytes
{
    public function bytes(int $length): string
    {
        return random_bytes($length);
    }
}

final readonly class FixedTokenBytes implements TokenBytes
{
    public function __construct(private string $bytes)
    {
    }

    public function bytes(int $length): string
    {
        return strlen($this->bytes) >= $length
            ? substr($this->bytes, 0, $length)
            : str_pad($this->bytes, $length, "\0");
    }
}

final readonly class DownloadTokenService
{
    public function __construct(
        private DownloadTokenRepository $repository,
        private string $appKey,
        private TokenBytes $tokenBytes = new RandomTokenBytes(),
    ) {
        if (trim($this->appKey) === '') {
            throw DownloadTokenException::invalidArgument('app_key is required.');
        }
    }

    public function issue(DownloadTokenIssueRequest $request): DownloadTokenIssueResult
    {
        $rawToken = self::base64Url($this->tokenBytes->bytes(32));
        $tokenHash = $this->hash($rawToken);
        $expiresAt = self::formatUtc(self::parseUtc($request->nowUtc)->add(new DateInterval('PT'.$request->ttlSeconds.'S')));
        $record = $this->repository->create(DownloadTokenRecord::issued(
            requestId: $request->requestId,
            tokenHash: $tokenHash,
            userId: $request->userId,
            resourceId: $request->resourceId,
            versionId: $request->versionId,
            entitlementId: $request->entitlementId,
            quotaReservationId: $request->quotaReservationId,
            issuedAt: $request->nowUtc,
            expiresAt: $expiresAt,
        ));

        return new DownloadTokenIssueResult(
            rawToken: $rawToken,
            tokenId: $record->id,
            requestId: $record->requestId,
            userId: $record->userId,
            resourceId: $record->resourceId,
            versionId: $record->versionId,
            entitlementId: $record->entitlementId,
            quotaReservationId: $record->quotaReservationId,
            ttlSeconds: $request->ttlSeconds,
            expiresAt: $record->expiresAt,
        );
    }

    public function consume(
        string $rawToken,
        int $userId,
        int $resourceId,
        int $versionId,
        string $nowUtc,
    ): DownloadTokenConsumeResult {
        if (trim($rawToken) === '' || $userId < 1 || $resourceId < 1 || $versionId < 1) {
            return DownloadTokenConsumeResult::fail('invalid_token_request');
        }
        if (DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $nowUtc) === false) {
            return DownloadTokenConsumeResult::fail('invalid_token_request');
        }

        $tokenHash = $this->hash($rawToken);
        $consumed = $this->repository->consumeIssuedToken(
            tokenHash: $tokenHash,
            userId: $userId,
            resourceId: $resourceId,
            versionId: $versionId,
            nowUtc: $nowUtc,
        );
        if ($consumed !== null) {
            return DownloadTokenConsumeResult::consumed($consumed);
        }

        $record = $this->repository->findByTokenHash($tokenHash);
        if ($record === null) {
            return DownloadTokenConsumeResult::fail('token_not_found');
        }
        if ($record->status === 'consumed' || $record->usedAt !== null) {
            return DownloadTokenConsumeResult::fail('token_already_used');
        }
        if (self::parseUtc($nowUtc) >= self::parseUtc($record->expiresAt)) {
            return DownloadTokenConsumeResult::fail('token_expired');
        }
        if ($record->userId !== $userId || $record->resourceId !== $resourceId || $record->versionId !== $versionId) {
            return DownloadTokenConsumeResult::fail('token_binding_mismatch');
        }

        return DownloadTokenConsumeResult::fail('token_not_consumed');
    }

    private function hash(string $rawToken): string
    {
        return hash_hmac('sha256', $rawToken, $this->appKey);
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function parseUtc(string $datetime): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $datetime);
        if ($parsed === false) {
            throw DownloadTokenException::invalidArgument('datetime must be ISO-8601.');
        }

        return $parsed->setTimezone(new DateTimeZone('UTC'));
    }

    private static function formatUtc(DateTimeImmutable $datetime): string
    {
        return $datetime->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
    }
}

final class DownloadTokenException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }

    public static function invalidArgument(string $message): self
    {
        return new self('invalid_argument', $message);
    }

    public static function duplicateRequestId(): self
    {
        return new self('duplicate_request_id', 'Download token request already exists.');
    }

    public static function duplicateTokenHash(): self
    {
        return new self('duplicate_token_hash', 'Download token hash already exists.');
    }

    public static function notFound(): self
    {
        return new self('token_not_found', 'Download token record was not found.');
    }

    public static function immutableIdentity(): self
    {
        return new self('immutable_token_identity', 'Download token identity fields are immutable.');
    }
}
