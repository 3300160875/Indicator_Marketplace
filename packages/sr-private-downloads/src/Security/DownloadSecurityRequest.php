<?php
declare(strict_types=1);

namespace StockResource\PrivateDownloads\Security;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class DownloadSecurityRequest
{
    public function __construct(
        public string $requestId,
        public int $userId,
        public int $resourceId,
        public int $versionId,
        public string $tokenFingerprint,
        public string $ipHash,
        public string $uaHash,
        public string $nowUtc,
    ) {
        if (! self::isUuid($this->requestId)) {
            throw new InvalidArgumentException('request_id must be a UUID.');
        }
        foreach (['user_id' => $this->userId, 'resource_id' => $this->resourceId, 'version_id' => $this->versionId] as $field => $value) {
            if ($value < 1) {
                throw new InvalidArgumentException($field.' must be positive.');
            }
        }
        if (! (bool) preg_match('/^[a-f0-9]{64}$/i', $this->tokenFingerprint)) {
            throw new InvalidArgumentException('token_fingerprint must be a sha256/HMAC hex hash.');
        }
        foreach (['ip_hash' => $this->ipHash, 'ua_hash' => $this->uaHash] as $field => $value) {
            if (! (bool) preg_match('/^[a-f0-9]{64}$/i', $value)) {
                throw new InvalidArgumentException($field.' must be a sha256 hex hash.');
            }
        }
        if (DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $this->nowUtc) === false) {
            throw new InvalidArgumentException('now_utc must be ISO-8601.');
        }
    }

    public function deviceFingerprint(): string
    {
        return hash('sha256', $this->ipHash.'|'.$this->uaHash);
    }

    private static function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }
}
