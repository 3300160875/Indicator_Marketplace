<?php
declare(strict_types=1);

namespace StockResource\PrivateDownloads\Security;

use InvalidArgumentException;

final readonly class RateLimitRule
{
    private function __construct(
        public string $dimension,
        public int $maxAttempts,
        public int $windowSeconds,
    ) {
        if (! in_array($this->dimension, ['user', 'ip', 'resource'], true)) {
            throw new InvalidArgumentException('rate limit dimension is invalid.');
        }
        if ($this->maxAttempts < 1 || $this->windowSeconds < 1) {
            throw new InvalidArgumentException('rate limit values must be positive.');
        }
    }

    public static function perUser(int $maxAttempts, int $windowSeconds): self
    {
        return new self('user', $maxAttempts, $windowSeconds);
    }

    public static function perIp(int $maxAttempts, int $windowSeconds): self
    {
        return new self('ip', $maxAttempts, $windowSeconds);
    }

    public static function perResource(int $maxAttempts, int $windowSeconds): self
    {
        return new self('resource', $maxAttempts, $windowSeconds);
    }

    public function bucket(DownloadSecurityRequest $request): string
    {
        return match ($this->dimension) {
            'user' => 'user:'.$request->userId,
            'ip' => 'ip:'.$request->ipHash,
            'resource' => 'resource:'.$request->resourceId,
        };
    }
}
