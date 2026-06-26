<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Storage;

final readonly class SignedUrl
{
    public function __construct(
        public string $url,
        public int $ttlSeconds,
        public int $expiresAt,
    ) {}
}
