<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Storage\Adapters;

use StockResource\PrivateDownloads\Storage\PutObjectOptions;
use StockResource\PrivateDownloads\Storage\SignedUrl;
use StockResource\PrivateDownloads\Storage\StorageException;
use StockResource\PrivateDownloads\Storage\StorageObjectKey;
use StockResource\PrivateDownloads\Storage\StorageService;
use StockResource\PrivateDownloads\Storage\StoredObject;

final class FakeStorageAdapter implements StorageService
{
    /** @var array<string, array{contents: string, object: StoredObject}> */
    private array $objects = [];

    public function __construct(private readonly string $bucket) {}

    public function put(StorageObjectKey $key, string $contents, PutObjectOptions $options, ?int $now = null): StoredObject
    {
        $options->assertPrivate();

        $object = new StoredObject(
            bucket: $this->bucket,
            key: $key,
            size: strlen($contents),
            contentType: $options->contentType,
            etag: hash('sha256', $contents),
            visibility: 'private',
        );
        $this->objects[$key->value] = ['contents' => $contents, 'object' => $object];

        return $object;
    }

    public function head(StorageObjectKey $key, ?int $now = null): StoredObject
    {
        if (! isset($this->objects[$key->value])) {
            throw StorageException::notFound($key->value);
        }

        return $this->objects[$key->value]['object'];
    }

    public function sign(StorageObjectKey $key, int $ttlSeconds, ?int $now = null): SignedUrl
    {
        $this->head($key);
        $now ??= time();
        $signature = hash_hmac('sha256', $this->bucket.'/'.$key->value.'/'.$ttlSeconds.'/'.$now, 'fake-secret');

        return new SignedUrl(
            url: 'fake://'.$this->bucket.'/'.$key->encodedPath().'?signature='.$signature.'&expires='.$ttlSeconds,
            ttlSeconds: $ttlSeconds,
            expiresAt: $now + $ttlSeconds,
        );
    }

    public function delete(StorageObjectKey $key, ?int $now = null): void
    {
        unset($this->objects[$key->value]);
    }

    public function anonymousGet(StorageObjectKey $key): string
    {
        $this->head($key);

        throw StorageException::accessDenied($key->value);
    }
}
