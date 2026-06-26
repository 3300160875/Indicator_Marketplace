<?php

declare(strict_types=1);

use StockResource\PrivateDownloads\Storage\Adapters\CurlHttpTransport;
use StockResource\PrivateDownloads\Storage\Adapters\FakeStorageAdapter;
use StockResource\PrivateDownloads\Storage\Adapters\MinioStorageAdapter;
use StockResource\PrivateDownloads\Storage\Adapters\RecordingHttpTransport;
use StockResource\PrivateDownloads\Storage\PutObjectOptions;
use StockResource\PrivateDownloads\Storage\StorageException;
use StockResource\PrivateDownloads\Storage\StorageObjectKey;

$root = dirname(__DIR__, 3);
$package = $root.'/packages/sr-private-downloads';

foreach ([
    '/src/Storage/StorageException.php',
    '/src/Storage/StorageObjectKey.php',
    '/src/Storage/PutObjectOptions.php',
    '/src/Storage/StoredObject.php',
    '/src/Storage/SignedUrl.php',
    '/src/Storage/StorageService.php',
    '/src/Storage/Adapters/HttpTransport.php',
    '/src/Storage/Adapters/CurlHttpTransport.php',
    '/src/Storage/Adapters/RecordingHttpTransport.php',
    '/src/Storage/Adapters/FakeStorageAdapter.php',
    '/src/Storage/Adapters/S3SignatureV4Signer.php',
    '/src/Storage/Adapters/MinioStorageAdapter.php',
] as $sourceFile) {
    require_once $package.$sourceFile;
}

function sr028_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr028_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

$fake = new FakeStorageAdapter(bucket: 'indicator-private');
$key = StorageObjectKey::fromString('resources/1001/v1.zip');
$stored = $fake->put($key, 'fixture-bytes', PutObjectOptions::private(contentType: 'application/zip'));

sr028_same('indicator-private', $stored->bucket, 'fake put records private bucket');
sr028_same('resources/1001/v1.zip', $stored->key->value, 'fake put records stable object key');
sr028_same(13, $stored->size, 'fake put records size');
sr028_same('application/zip', $stored->contentType, 'fake put records content type');
sr028_same('private', $stored->visibility, 'fake put enforces private visibility');

$head = $fake->head($key);
sr028_same($stored->etag, $head->etag, 'head returns stored etag');

$signed = $fake->sign($key, ttlSeconds: 120, now: 1_800_000_000);
sr028_assert(str_starts_with($signed->url, 'fake://indicator-private/resources/1001/v1.zip?signature='), 'fake sign returns deterministic private URL');
sr028_same(120, $signed->ttlSeconds, 'signed URL records TTL');
sr028_same(1_800_000_120, $signed->expiresAt, 'signed URL records expiry timestamp');

try {
    $fake->anonymousGet($key);
    throw new RuntimeException('anonymous access must fail for private object');
} catch (StorageException $exception) {
    sr028_same('access_denied', $exception->codeName, 'anonymous access maps to access_denied');
}

$fake->delete($key);
try {
    $fake->head($key);
    throw new RuntimeException('deleted object must not be found');
} catch (StorageException $exception) {
    sr028_same('not_found', $exception->codeName, 'missing object maps to not_found');
}

try {
    StorageObjectKey::fromString('../secrets.zip');
    throw new RuntimeException('unsafe object key must fail');
} catch (StorageException $exception) {
    sr028_same('invalid_key', $exception->codeName, 'unsafe object keys map to invalid_key');
}

try {
    $fake->put(StorageObjectKey::fromString('resources/public.zip'), 'public', new PutObjectOptions('public-read', 'application/zip'));
    throw new RuntimeException('public ACL must fail');
} catch (StorageException $exception) {
    sr028_same('invalid_acl', $exception->codeName, 'public ACL maps to invalid_acl');
}

$transport = new RecordingHttpTransport([
    'PUT' => [200, ['ETag' => '"abc123"'], ''],
    'HEAD' => [200, ['Content-Length' => '13', 'Content-Type' => 'application/zip', 'ETag' => '"abc123"'], ''],
    'DELETE' => [204, [], ''],
]);
$minio = new MinioStorageAdapter(
    endpoint: 'http://minio:9000',
    region: 'us-east-1',
    bucket: 'indicator-private',
    accessKey: 'minioadmin-local',
    secretKey: 'minioadmin-local',
    transport: $transport,
    pathStyle: true,
);

$minioStored = $minio->put($key, 'fixture-bytes', PutObjectOptions::private('application/zip'), now: 1_800_000_000);
sr028_same('indicator-private', $minioStored->bucket, 'minio put records bucket');
sr028_same('abc123', $minioStored->etag, 'minio put normalizes etag');
sr028_same('private', $transport->requests[0]['headers']['x-amz-acl'] ?? null, 'minio put sends private ACL');
sr028_assert(isset($transport->requests[0]['headers']['Authorization']), 'minio put signs request');

$minioHead = $minio->head($key, now: 1_800_000_000);
sr028_same(13, $minioHead->size, 'minio head parses content length');

$minioSigned = $minio->sign($key, ttlSeconds: 120, now: 1_800_000_000);
sr028_assert(str_contains($minioSigned->url, 'X-Amz-Expires=120'), 'minio signed URL contains TTL');
sr028_assert(str_contains($minioSigned->url, 'X-Amz-Signature='), 'minio signed URL contains signature');
sr028_assert(! str_contains($minioSigned->url, 'secret'), 'minio signed URL does not leak secret key');

$minio->delete($key, now: 1_800_000_000);
sr028_same('DELETE', $transport->requests[2]['method'], 'minio delete uses DELETE');

$notFoundTransport = new RecordingHttpTransport([
    'HEAD' => [404, [], ''],
]);
$notFound = new MinioStorageAdapter('http://minio:9000', 'us-east-1', 'indicator-private', 'ak', 'sk', $notFoundTransport);
try {
    $notFound->head($key);
    throw new RuntimeException('minio missing object must fail');
} catch (StorageException $exception) {
    sr028_same('not_found', $exception->codeName, 'minio 404 maps to not_found');
}

$liveEndpoint = getenv('SR028_MINIO_ENDPOINT') ?: 'http://127.0.0.1:9002';
$liveBucket = getenv('SR028_MINIO_BUCKET') ?: 'indicator-assets';
$liveAccessKey = getenv('SR028_MINIO_ACCESS_KEY') ?: 'minioadmin-local';
$liveSecretKey = getenv('SR028_MINIO_SECRET_KEY') ?: 'minioadmin-local';
$health = @file_get_contents($liveEndpoint.'/minio/health/live');
if ($health !== false && extension_loaded('curl')) {
    $liveKey = StorageObjectKey::fromString('sr028/tmp-'.bin2hex(random_bytes(4)).'.txt');
    $live = new MinioStorageAdapter(
        endpoint: $liveEndpoint,
        region: 'us-east-1',
        bucket: $liveBucket,
        accessKey: $liveAccessKey,
        secretKey: $liveSecretKey,
        transport: new CurlHttpTransport,
    );

    $live->put($liveKey, 'live-minio-fixture', PutObjectOptions::private('text/plain'));
    $liveHead = $live->head($liveKey);
    sr028_same(18, $liveHead->size, 'live MinIO head returns uploaded object size');
    $liveSigned = $live->sign($liveKey, ttlSeconds: 60);
    sr028_assert(str_contains($liveSigned->url, 'X-Amz-Expires=60'), 'live MinIO signed URL contains TTL');

    $anonymous = curl_init($liveEndpoint.'/'.$liveBucket.'/'.$liveKey->encodedPath());
    sr028_assert($anonymous !== false, 'anonymous curl handle is created');
    curl_setopt_array($anonymous, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    curl_exec($anonymous);
    $anonymousStatus = (int) curl_getinfo($anonymous, CURLINFO_RESPONSE_CODE);
    curl_close($anonymous);
    sr028_assert(in_array($anonymousStatus, [403, 404], true), 'live MinIO object is not anonymously readable');

    $live->delete($liveKey);
    try {
        $live->head($liveKey);
        throw new RuntimeException('live deleted object must not be found');
    } catch (StorageException $exception) {
        sr028_same('not_found', $exception->codeName, 'live MinIO delete removes object');
    }
}

echo "SR-028 storage checks passed.\n";
