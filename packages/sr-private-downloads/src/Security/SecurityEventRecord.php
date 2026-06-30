<?php
declare(strict_types=1);

namespace StockResource\PrivateDownloads\Security;

final readonly class SecurityEventRecord
{
    /**
     * @param array<string, int|string|null> $metadata
     */
    public function __construct(
        public string $action,
        public string $code,
        public string $requestId,
        public int $userId,
        public int $resourceId,
        public string $ipHash,
        public string $uaHash,
        public array $metadata,
    ) {
    }

    public static function blocked(DownloadSecurityRequest $request, string $code, ?string $retryAfterUtc = null): self
    {
        return self::fromRequest('download.security.blocked', $code, $request, $retryAfterUtc);
    }

    public static function warning(DownloadSecurityRequest $request, string $code): self
    {
        return self::fromRequest('download.security.warning', $code, $request, null);
    }

    private static function fromRequest(
        string $action,
        string $code,
        DownloadSecurityRequest $request,
        ?string $retryAfterUtc,
    ): self {
        return new self(
            action: $action,
            code: $code,
            requestId: $request->requestId,
            userId: $request->userId,
            resourceId: $request->resourceId,
            ipHash: $request->ipHash,
            uaHash: $request->uaHash,
            metadata: [
                'version_id' => $request->versionId,
                'token_fingerprint_hash' => hash('sha256', $request->tokenFingerprint),
                'retry_after_utc' => $retryAfterUtc,
            ],
        );
    }
}
