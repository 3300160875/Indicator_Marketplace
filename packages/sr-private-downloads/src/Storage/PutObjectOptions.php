<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Storage;

final readonly class PutObjectOptions
{
    public function __construct(
        public string $visibility = 'private',
        public string $contentType = 'application/octet-stream',
    ) {}

    public static function private(string $contentType = 'application/octet-stream'): self
    {
        return new self('private', $contentType);
    }

    public function assertPrivate(): void
    {
        if ($this->visibility !== 'private') {
            throw StorageException::invalidAcl($this->visibility);
        }
    }
}
