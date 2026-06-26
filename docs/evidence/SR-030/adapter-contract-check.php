<?php

declare(strict_types=1);

use StockResource\PrivateDownloads\Storage\Adapters\ObjectStorageProvider;
use StockResource\PrivateDownloads\Storage\Adapters\ProductionStorageAdapterFactory;
use StockResource\PrivateDownloads\Storage\Adapters\RecordingHttpTransport;
use StockResource\PrivateDownloads\Storage\Adapters\S3CompatibleAdapterConfig;
use StockResource\PrivateDownloads\Storage\PutObjectOptions;
use StockResource\PrivateDownloads\Storage\StorageObjectKey;
use StockResource\PrivateDownloads\Storage\StorageService;

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
    '/src/Storage/Adapters/RecordingHttpTransport.php',
    '/src/Storage/Adapters/S3SignatureV4Signer.php',
    '/src/Storage/Adapters/MinioStorageAdapter.php',
    '/src/Storage/Adapters/ObjectStorageProvider.php',
    '/src/Storage/Adapters/S3CompatibleAdapterConfig.php',
    '/src/Storage/Adapters/ProductionStorageAdapterFactory.php',
] as $sourceFile) {
    require_once $package.$sourceFile;
}

function sr030_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr030_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

$key = StorageObjectKey::fromString('resources/1001/v1.zip');

$cases = [
    ObjectStorageProvider::S3->value => [
        'provider' => ObjectStorageProvider::S3,
        'endpoint' => 'https://s3.us-east-1.amazonaws.com',
        'region' => 'us-east-1',
        'expected_host' => 'prod-bucket.s3.us-east-1.amazonaws.com',
    ],
    ObjectStorageProvider::COS->value => [
        'provider' => ObjectStorageProvider::COS,
        'endpoint' => 'https://cos.ap-shanghai.myqcloud.com',
        'region' => 'ap-shanghai',
        'expected_host' => 'prod-bucket.cos.ap-shanghai.myqcloud.com',
    ],
    ObjectStorageProvider::OSS->value => [
        'provider' => ObjectStorageProvider::OSS,
        'endpoint' => 'https://oss-cn-hangzhou.aliyuncs.com',
        'region' => 'oss-cn-hangzhou',
        'expected_host' => 'prod-bucket.oss-cn-hangzhou.aliyuncs.com',
    ],
];

foreach ($cases as $label => $case) {
    $transport = new RecordingHttpTransport([
        'PUT' => [200, ['ETag' => '"'.$label.'-etag"'], ''],
    ]);
    $config = S3CompatibleAdapterConfig::forProvider(
        provider: $case['provider'],
        endpoint: $case['endpoint'],
        region: $case['region'],
        bucket: 'prod-bucket',
        accessKey: 'access-key',
        secretKey: 'secret-key',
    );
    $adapter = ProductionStorageAdapterFactory::create($config, $transport);

    sr030_assert($adapter instanceof StorageService, $label.' adapter implements StorageService');
    $adapter->put($key, 'bytes', PutObjectOptions::private('application/zip'), now: 1_800_000_000);

    $requestUrl = $transport->requests[0]['url'];
    sr030_same('https://'.$case['expected_host'].'/resources/1001/v1.zip', $requestUrl, $label.' adapter encapsulates virtual-hosted endpoint shape');
    sr030_same('private', $transport->requests[0]['headers']['x-amz-acl'] ?? null, $label.' adapter keeps private ACL');
    sr030_assert(isset($transport->requests[0]['headers']['Authorization']), $label.' adapter signs with shared S3-compatible contract');
}

$minioTransport = new RecordingHttpTransport([
    'PUT' => [200, ['ETag' => '"minio-etag"'], ''],
]);
$minioConfig = S3CompatibleAdapterConfig::forProvider(
    provider: ObjectStorageProvider::MINIO,
    endpoint: 'http://127.0.0.1:9002',
    region: 'us-east-1',
    bucket: 'indicator-assets',
    accessKey: 'minioadmin-local',
    secretKey: 'minioadmin-local',
);
$minioAdapter = ProductionStorageAdapterFactory::create($minioConfig, $minioTransport);
$minioAdapter->put($key, 'bytes', PutObjectOptions::private('application/zip'), now: 1_800_000_000);
sr030_same('http://127.0.0.1:9002/indicator-assets/resources/1001/v1.zip', $minioTransport->requests[0]['url'], 'MinIO keeps path-style endpoint shape');

$adapterFiles = glob($package.'/src/Storage/Adapters/*.php');
sr030_assert(is_array($adapterFiles), 'adapter files are discoverable');
$source = '';
foreach ($adapterFiles as $adapterFile) {
    $source .= (string) file_get_contents($adapterFile)."\n";
}
foreach (['Aws\\\\', 'Qcloud\\\\', 'OSS\\\\', 'AlibabaCloud\\\\', 'TencentCloud\\\\', 'new S3Client', 'vendor/autoload'] as $forbidden) {
    sr030_assert(! str_contains($source, $forbidden), 'vendor SDK does not leak into adapter layer: '.$forbidden);
}

echo "SR-030 production adapter contract checks passed.\n";
