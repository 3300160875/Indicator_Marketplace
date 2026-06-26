<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Storage\Adapters;

use StockResource\PrivateDownloads\Storage\PutObjectOptions;
use StockResource\PrivateDownloads\Storage\SignedUrl;
use StockResource\PrivateDownloads\Storage\StorageException;
use StockResource\PrivateDownloads\Storage\StorageObjectKey;
use StockResource\PrivateDownloads\Storage\StorageService;
use StockResource\PrivateDownloads\Storage\StoredObject;

final readonly class MinioStorageAdapter implements StorageService
{
    private S3SignatureV4Signer $signer;

    public function __construct(
        private string $endpoint,
        private string $region,
        private string $bucket,
        private string $accessKey,
        private string $secretKey,
        private HttpTransport $transport,
        private bool $pathStyle = true,
    ) {
        $this->signer = new S3SignatureV4Signer($this->region, $this->accessKey, $this->secretKey);
    }

    public function put(StorageObjectKey $key, string $contents, PutObjectOptions $options, ?int $now = null): StoredObject
    {
        $options->assertPrivate();
        $now ??= time();
        $url = $this->objectUrl($key);
        $headers = $this->signer->authorizationHeaders('PUT', $url, [
            'Content-Type' => $options->contentType,
            'x-amz-acl' => 'private',
        ], hash('sha256', $contents), $now);
        [$status, $responseHeaders] = $this->transport->request('PUT', $url, $headers, $contents);
        $this->assertSuccess($status, $key);

        return new StoredObject(
            bucket: $this->bucket,
            key: $key,
            size: strlen($contents),
            contentType: $options->contentType,
            etag: $this->normalizeEtag($responseHeaders['ETag'] ?? $responseHeaders['etag'] ?? hash('sha256', $contents)),
            visibility: 'private',
        );
    }

    public function head(StorageObjectKey $key, ?int $now = null): StoredObject
    {
        $now ??= time();
        $url = $this->objectUrl($key);
        $headers = $this->signer->authorizationHeaders('HEAD', $url, [], 'UNSIGNED-PAYLOAD', $now);
        [$status, $responseHeaders] = $this->transport->request('HEAD', $url, $headers);
        $this->assertSuccess($status, $key);

        return new StoredObject(
            bucket: $this->bucket,
            key: $key,
            size: max(0, (int) ($responseHeaders['Content-Length'] ?? $responseHeaders['content-length'] ?? 0)),
            contentType: (string) ($responseHeaders['Content-Type'] ?? $responseHeaders['content-type'] ?? 'application/octet-stream'),
            etag: $this->normalizeEtag($responseHeaders['ETag'] ?? $responseHeaders['etag'] ?? ''),
            visibility: 'private',
        );
    }

    public function sign(StorageObjectKey $key, int $ttlSeconds, ?int $now = null): SignedUrl
    {
        $now ??= time();

        return new SignedUrl(
            url: $this->signer->presignedUrl($this->endpoint, $this->bucket, $key, $ttlSeconds, $now, $this->pathStyle),
            ttlSeconds: $ttlSeconds,
            expiresAt: $now + $ttlSeconds,
        );
    }

    public function delete(StorageObjectKey $key, ?int $now = null): void
    {
        $now ??= time();
        $url = $this->objectUrl($key);
        $headers = $this->signer->authorizationHeaders('DELETE', $url, [], 'UNSIGNED-PAYLOAD', $now);
        [$status] = $this->transport->request('DELETE', $url, $headers);
        $this->assertSuccess($status, $key);
    }

    private function objectUrl(StorageObjectKey $key): string
    {
        $base = rtrim($this->endpoint, '/');
        if ($this->pathStyle) {
            return $base.'/'.rawurlencode($this->bucket).'/'.$key->encodedPath();
        }

        return $base.'/'.$key->encodedPath();
    }

    private function assertSuccess(int $status, StorageObjectKey $key): void
    {
        if ($status >= 200 && $status < 300) {
            return;
        }

        if ($status === 404) {
            throw StorageException::notFound($key->value);
        }

        if ($status === 403) {
            throw StorageException::accessDenied($key->value);
        }

        throw StorageException::unavailable('Storage request failed with status '.$status, $status);
    }

    private function normalizeEtag(string $etag): string
    {
        return trim($etag, "\" \t\n\r\0\x0B");
    }
}
