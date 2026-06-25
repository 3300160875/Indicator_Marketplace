<?php
declare(strict_types=1);

namespace StockResource\Contracts\Dto;

use StockResource\Contracts\Enum\AccessSource;
use StockResource\Contracts\Exception\ValidationException;
use StockResource\Contracts\Value\RequestId;
use StockResource\Contracts\Value\UtcDateTime;

final readonly class DownloadTokenResponse
{
    public function __construct(
        public RequestId $requestId,
        public string $downloadUrl,
        public UtcDateTime $expiresAt,
        public AccessSource $accessSource,
        public ?int $remainingQuota,
    ) {
        if ('' === trim($downloadUrl)) {
            throw new ValidationException('Download URL must not be empty.');
        }
        if (null !== $remainingQuota && $remainingQuota < 0) {
            throw new ValidationException('Remaining quota must be non-negative.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId->toString(),
            'download_url' => $this->downloadUrl,
            'expires_at' => $this->expiresAt->toString(),
            'access_source' => $this->accessSource->value,
            'remaining_quota' => $this->remainingQuota,
        ];
    }
}
