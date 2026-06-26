<?php

declare(strict_types=1);

namespace StockResource\Core\Version\Upload;

use StockResource\Core\Version\ResourceVersion;
use StockResource\Core\Version\ResourceVersionRepository;
use StockResource\Core\Version\ResourceVersionScanStatus;
use StockResource\Core\Version\ResourceVersionStatus;
use StockResource\PrivateDownloads\Scan\FileScanner;
use StockResource\PrivateDownloads\Scan\ScanResult;
use StockResource\PrivateDownloads\Storage\PutObjectOptions;
use StockResource\PrivateDownloads\Storage\StorageObjectKey;
use StockResource\PrivateDownloads\Storage\StorageService;

final readonly class VersionUploadService
{
    public function __construct(
        private ResourceVersionRepository $repository,
        private StorageService $storage,
        private FileScanner $scanner,
        private VersionUploadPolicy $policy,
        private string $storageProvider,
        private string $storageBucket,
    ) {}

    public function uploadAndActivate(
        int $resourceId,
        int $versionId,
        string $versionLabel,
        UploadedVersionFile $uploadedFile,
        int $createdBy,
        int $approvedBy,
        string $now,
        ?string $releaseNotes = null,
    ): VersionUploadResult {
        $mimeType = $this->policy->assertAccepts($uploadedFile);
        $sha256 = hash('sha256', $uploadedFile->contents);
        $quarantineKey = $this->quarantineKey($resourceId, $versionId, $sha256, $uploadedFile->originalFilename);
        $finalKey = $this->finalKey($resourceId, $versionId, $sha256, $uploadedFile->originalFilename);

        $this->storage->put(
            $quarantineKey,
            $uploadedFile->contents,
            PutObjectOptions::private($mimeType),
        );

        $scanResult = $this->scanner->scan($quarantineKey, $sha256, $mimeType, $uploadedFile->size());
        if ($scanResult->verdict !== 'clean') {
            $version = $this->createVersion(
                resourceId: $resourceId,
                versionId: $versionId,
                versionLabel: $versionLabel,
                status: ResourceVersionStatus::Scanning,
                isCurrent: false,
                storageKey: $quarantineKey,
                uploadedFile: $uploadedFile,
                mimeType: $mimeType,
                sha256: $sha256,
                scanStatus: $this->scanStatus($scanResult),
                scanResult: $scanResult,
                releaseNotes: $releaseNotes,
                createdBy: $createdBy,
                approvedBy: null,
                now: $now,
            );

            $this->repository->create($version);

            return new VersionUploadResult($version, $quarantineKey, $finalKey, $scanResult);
        }

        $this->storage->put(
            $finalKey,
            $uploadedFile->contents,
            PutObjectOptions::private($mimeType),
        );
        $this->storage->delete($quarantineKey);

        $reviewVersion = $this->createVersion(
            resourceId: $resourceId,
            versionId: $versionId,
            versionLabel: $versionLabel,
            status: ResourceVersionStatus::Review,
            isCurrent: false,
            storageKey: $finalKey,
            uploadedFile: $uploadedFile,
            mimeType: $mimeType,
            sha256: $sha256,
            scanStatus: ResourceVersionScanStatus::Clean,
            scanResult: $scanResult,
            releaseNotes: $releaseNotes,
            createdBy: $createdBy,
            approvedBy: null,
            now: $now,
        );

        $this->repository->create($reviewVersion);
        $activated = $this->repository->activateCurrent($resourceId, $versionId, $approvedBy, $now);

        return new VersionUploadResult($activated, $quarantineKey, $finalKey, $scanResult);
    }

    private function quarantineKey(int $resourceId, int $versionId, string $sha256, string $filename): StorageObjectKey
    {
        return StorageObjectKey::fromString('quarantine/resources/'.$resourceId.'/versions/'.$versionId.'/'.$sha256.'.'.$this->extension($filename));
    }

    private function finalKey(int $resourceId, int $versionId, string $sha256, string $filename): StorageObjectKey
    {
        return StorageObjectKey::fromString('resources/'.$resourceId.'/versions/'.$versionId.'/'.$sha256.'.'.$this->extension($filename));
    }

    private function extension(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';

        return $extension;
    }

    /**
     * @param  array<string, mixed>  $scanResult
     */
    private function createVersion(
        int $resourceId,
        int $versionId,
        string $versionLabel,
        ResourceVersionStatus $status,
        bool $isCurrent,
        StorageObjectKey $storageKey,
        UploadedVersionFile $uploadedFile,
        string $mimeType,
        string $sha256,
        ResourceVersionScanStatus $scanStatus,
        ScanResult $scanResult,
        ?string $releaseNotes,
        int $createdBy,
        ?int $approvedBy,
        string $now,
    ): ResourceVersion {
        return ResourceVersion::fromArray([
            'id' => $versionId,
            'resource_id' => $resourceId,
            'version_label' => $versionLabel,
            'status' => $status->value,
            'is_current' => $isCurrent,
            'storage_provider' => $this->storageProvider,
            'storage_bucket' => $this->storageBucket,
            'storage_key' => $storageKey->value,
            'original_filename' => $uploadedFile->originalFilename,
            'mime_type' => $mimeType,
            'file_size' => $uploadedFile->size(),
            'sha256' => $sha256,
            'compatibility' => [],
            'scan_status' => $scanStatus->value,
            'scan_result' => [
                'verdict' => $scanResult->verdict,
                'details' => $scanResult->details,
            ],
            'release_notes' => $releaseNotes,
            'created_by' => $createdBy,
            'approved_by' => $approvedBy,
            'activated_at' => null,
            'suspended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function scanStatus(ScanResult $scanResult): ResourceVersionScanStatus
    {
        return match ($scanResult->verdict) {
            'infected' => ResourceVersionScanStatus::Infected,
            'failed' => ResourceVersionScanStatus::Failed,
            default => ResourceVersionScanStatus::Pending,
        };
    }
}
