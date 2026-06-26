<?php

declare(strict_types=1);

namespace StockResource\Core\Version\Upload;

final readonly class UploadedVersionFile
{
    public function __construct(
        public string $originalFilename,
        public string $contents,
        public string $clientMimeType = 'application/octet-stream',
        public int $archiveEntryCount = 1,
        public int $archiveMaxDepth = 1,
        public int $archiveExpandedBytes = 0,
    ) {}

    public function size(): int
    {
        return strlen($this->contents);
    }
}
