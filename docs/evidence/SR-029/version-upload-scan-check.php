<?php

declare(strict_types=1);

use StockResource\Core\Version\InMemoryResourceVersionRepository;
use StockResource\Core\Version\ResourceVersionScanStatus;
use StockResource\Core\Version\ResourceVersionStatus;
use StockResource\Core\Version\Upload\UploadedVersionFile;
use StockResource\Core\Version\Upload\VersionUploadException;
use StockResource\Core\Version\Upload\VersionUploadPolicy;
use StockResource\Core\Version\Upload\VersionUploadService;
use StockResource\PrivateDownloads\Scan\RecordingFileScanner;
use StockResource\PrivateDownloads\Scan\ScanResult;
use StockResource\PrivateDownloads\Storage\Adapters\FakeStorageAdapter;
use StockResource\PrivateDownloads\Storage\StorageException;

$root = dirname(__DIR__, 3);
$core = $root.'/packages/sr-core';
$privateDownloads = $root.'/packages/sr-private-downloads';

foreach ([
    '/src/Version/ResourceVersionStatus.php',
    '/src/Version/ResourceVersionScanStatus.php',
    '/src/Version/ResourceVersion.php',
    '/src/Version/ResourceVersionRepository.php',
    '/src/Version/InMemoryResourceVersionRepository.php',
    '/src/Version/Upload/UploadedVersionFile.php',
    '/src/Version/Upload/VersionUploadException.php',
    '/src/Version/Upload/VersionUploadPolicy.php',
    '/src/Version/Upload/VersionUploadResult.php',
    '/src/Version/Upload/VersionUploadService.php',
] as $sourceFile) {
    require_once $core.$sourceFile;
}

foreach ([
    '/src/Storage/StorageException.php',
    '/src/Storage/StorageObjectKey.php',
    '/src/Storage/PutObjectOptions.php',
    '/src/Storage/StoredObject.php',
    '/src/Storage/SignedUrl.php',
    '/src/Storage/StorageService.php',
    '/src/Storage/Adapters/FakeStorageAdapter.php',
    '/src/Scan/FileScanner.php',
    '/src/Scan/ScanResult.php',
    '/src/Scan/RecordingFileScanner.php',
] as $sourceFile) {
    require_once $privateDownloads.$sourceFile;
}

function sr029_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr029_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr029_expect_upload_error(string $codeName, callable $callback): void
{
    try {
        $callback();
    } catch (VersionUploadException $exception) {
        sr029_same($codeName, $exception->codeName, 'upload exception code');

        return;
    }

    throw new RuntimeException('Expected upload exception '.$codeName);
}

$policy = new VersionUploadPolicy(
    maxBytes: 1024,
    allowedMimeTypes: ['application/zip'],
    maxArchiveEntries: 3,
    maxArchiveDepth: 2,
    maxExpandedBytes: 4096,
    maxCompressionRatio: 20.0,
);
$storage = new FakeStorageAdapter('indicator-assets');
$repository = new InMemoryResourceVersionRepository;
$cleanScanner = new RecordingFileScanner(ScanResult::clean(['engine' => 'fake-clamav']));
$service = new VersionUploadService(
    repository: $repository,
    storage: $storage,
    scanner: $cleanScanner,
    policy: $policy,
    storageProvider: 'minio',
    storageBucket: 'indicator-assets',
);
$cleanFile = new UploadedVersionFile(
    originalFilename: 'tdx-trend.zip',
    contents: "PK\x03\x04clean-indicator-package",
    clientMimeType: 'application/octet-stream',
    archiveEntryCount: 2,
    archiveMaxDepth: 1,
    archiveExpandedBytes: 128,
);

$result = $service->uploadAndActivate(
    resourceId: 1001,
    versionId: 5001,
    versionLabel: '1.2.0',
    uploadedFile: $cleanFile,
    createdBy: 7,
    approvedBy: 9,
    now: '2026-06-26T09:30:00Z',
    releaseNotes: 'Clean test release.',
);

sr029_same(ResourceVersionStatus::Active, $result->version->status, 'clean upload activates version');
sr029_same(ResourceVersionScanStatus::Clean, $result->version->scanStatus, 'clean upload stores clean scan status');
sr029_assert($result->version->isCurrent, 'clean upload is current after activation');
sr029_same(hash('sha256', $cleanFile->contents), $result->version->sha256, 'clean upload stores server hash');
sr029_same('application/zip', $result->version->mimeType, 'server-side MIME sniffing overrides client MIME');
sr029_assert(str_starts_with($cleanScanner->scans[0]['key'], 'quarantine/resources/1001/versions/5001/'), 'scanner receives quarantined object first');
sr029_assert(str_starts_with($result->quarantineKey->value, 'quarantine/resources/1001/versions/5001/'), 'quarantine key uses isolated prefix');
sr029_assert(str_starts_with($result->finalKey->value, 'resources/1001/versions/5001/'), 'final key uses private resource prefix');
sr029_same($result->finalKey->value, $result->version->storageKey, 'activated version references final key');
sr029_same('private', $storage->head($result->finalKey)->visibility, 'final object remains private');
try {
    $storage->head($result->quarantineKey);
    throw new RuntimeException('quarantine object should be removed after successful activation');
} catch (StorageException $exception) {
    sr029_same('not_found', $exception->codeName, 'quarantine is removed after activation');
}
sr029_same([1001], $repository->transactionLockLog(), 'activation uses resource transaction lock');

$infectedScanner = new RecordingFileScanner(ScanResult::infected(['signature' => 'EICAR-Test-File']));
$infectedService = new VersionUploadService(
    repository: $repository,
    storage: $storage,
    scanner: $infectedScanner,
    policy: $policy,
    storageProvider: 'minio',
    storageBucket: 'indicator-assets',
);
$infectedFile = new UploadedVersionFile(
    originalFilename: 'infected.zip',
    contents: "PK\x03\x04infected-package",
    clientMimeType: 'application/zip',
    archiveEntryCount: 1,
    archiveMaxDepth: 1,
    archiveExpandedBytes: 64,
);
$infected = $infectedService->uploadAndActivate(
    resourceId: 1001,
    versionId: 5002,
    versionLabel: '1.2.1',
    uploadedFile: $infectedFile,
    createdBy: 7,
    approvedBy: 9,
    now: '2026-06-26T09:31:00Z',
    releaseNotes: 'Infected test release.',
);
sr029_same(ResourceVersionStatus::Scanning, $infected->version->status, 'infected upload stays in scanning state');
sr029_same(ResourceVersionScanStatus::Infected, $infected->version->scanStatus, 'infected upload stores infected scan status');
sr029_assert(! $infected->version->isCurrent, 'infected upload is not current');
sr029_same($infected->quarantineKey->value, $infected->version->storageKey, 'infected version remains quarantined');
sr029_same('private', $storage->head($infected->quarantineKey)->visibility, 'quarantine object remains private');
try {
    $storage->head($infected->finalKey);
    throw new RuntimeException('infected upload must not create a final object');
} catch (StorageException $exception) {
    sr029_same('not_found', $exception->codeName, 'infected upload has no final object');
}
sr029_same(5001, $repository->currentForResource(1001)?->id, 'infected upload does not replace current version');

sr029_expect_upload_error('invalid_mime', function () use ($service): void {
    $service->uploadAndActivate(
        resourceId: 1001,
        versionId: 5003,
        versionLabel: 'bad-mime',
        uploadedFile: new UploadedVersionFile('shell.php', '<?php echo "x";', 'application/zip', 1, 1, 32),
        createdBy: 7,
        approvedBy: 9,
        now: '2026-06-26T09:32:00Z',
    );
});

sr029_expect_upload_error('archive_limit_exceeded', function () use ($service): void {
    $service->uploadAndActivate(
        resourceId: 1001,
        versionId: 5004,
        versionLabel: 'too-many-files',
        uploadedFile: new UploadedVersionFile('too-many.zip', "PK\x03\x04many", 'application/zip', 4, 1, 64),
        createdBy: 7,
        approvedBy: 9,
        now: '2026-06-26T09:33:00Z',
    );
});

sr029_expect_upload_error('compression_ratio_exceeded', function () use ($service): void {
    $service->uploadAndActivate(
        resourceId: 1001,
        versionId: 5005,
        versionLabel: 'zip-bomb',
        uploadedFile: new UploadedVersionFile('zip-bomb.zip', "PK\x03\x04tiny", 'application/zip', 1, 1, 1000),
        createdBy: 7,
        approvedBy: 9,
        now: '2026-06-26T09:34:00Z',
    );
});

$source = '';
foreach ([
    $core.'/src/Version/Upload',
    $privateDownloads.'/src/Scan',
] as $directory) {
    foreach (glob($directory.'/*.php') ?: [] as $file) {
        $source .= (string) file_get_contents($file)."\n";
    }
}
foreach (['wp_upload_dir', 'wp_handle_upload', 'wp_insert_attachment', 'media_handle_upload', 'public-read'] as $forbidden) {
    sr029_assert(! str_contains($source, $forbidden), 'upload and scan code avoids public media path: '.$forbidden);
}

echo "SR-029 version upload and scan checks passed.\n";
