<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Storage;

interface StorageService
{
    public function put(StorageObjectKey $key, string $contents, PutObjectOptions $options, ?int $now = null): StoredObject;

    public function head(StorageObjectKey $key, ?int $now = null): StoredObject;

    public function sign(StorageObjectKey $key, int $ttlSeconds, ?int $now = null): SignedUrl;

    public function delete(StorageObjectKey $key, ?int $now = null): void;
}
