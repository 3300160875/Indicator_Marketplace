<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Storage;

final readonly class StoredObject
{
    public function __construct(
        public string $bucket,
        public StorageObjectKey $key,
        public int $size,
        public string $contentType,
        public string $etag,
        public string $visibility = 'private',
    ) {}
}
