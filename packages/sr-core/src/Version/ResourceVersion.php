<?php
declare(strict_types=1);

namespace StockResource\Core\Version;

use RuntimeException;

final readonly class ResourceVersion
{
    /**
     * @param array<string, mixed> $compatibility
     * @param array<string, mixed> $scanResult
     */
    public function __construct(
        public int $id,
        public int $resourceId,
        public string $versionLabel,
        public ResourceVersionStatus $status,
        public bool $isCurrent,
        public ?string $storageProvider,
        public ?string $storageBucket,
        public ?string $storageKey,
        public ?string $originalFilename,
        public ?string $mimeType,
        public ?int $fileSize,
        public ?string $sha256,
        public array $compatibility,
        public ResourceVersionScanStatus $scanStatus,
        public array $scanResult,
        public ?string $releaseNotes,
        public int $createdBy,
        public ?int $approvedBy,
        public ?string $activatedAt,
        public ?string $suspendedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
        if ($this->id <= 0) {
            throw new RuntimeException('Resource version id must be positive.');
        }
        if ($this->resourceId <= 0) {
            throw new RuntimeException('Resource version resource id must be positive.');
        }
        if ($this->versionLabel === '') {
            throw new RuntimeException('Resource version label is required.');
        }
        if ($this->fileSize !== null && $this->fileSize < 0) {
            throw new RuntimeException('Resource version file size cannot be negative.');
        }
        if ($this->sha256 !== null && ! preg_match('/^[a-f0-9]{64}$/', $this->sha256)) {
            throw new RuntimeException('Resource version sha256 must be a SHA-256 hex digest.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: max(0, (int) ($data['id'] ?? 0)),
            resourceId: max(0, (int) ($data['resource_id'] ?? 0)),
            versionLabel: trim((string) ($data['version_label'] ?? '')),
            status: ResourceVersionStatus::from((string) ($data['status'] ?? ResourceVersionStatus::Draft->value)),
            isCurrent: (bool) ($data['is_current'] ?? false),
            storageProvider: self::nullableString($data['storage_provider'] ?? null),
            storageBucket: self::nullableString($data['storage_bucket'] ?? null),
            storageKey: self::nullableString($data['storage_key'] ?? null),
            originalFilename: self::nullableString($data['original_filename'] ?? null),
            mimeType: self::nullableString($data['mime_type'] ?? null),
            fileSize: isset($data['file_size']) ? max(0, (int) $data['file_size']) : null,
            sha256: self::normalizeSha256($data['sha256'] ?? null),
            compatibility: is_array($data['compatibility'] ?? null) ? $data['compatibility'] : [],
            scanStatus: ResourceVersionScanStatus::from((string) ($data['scan_status'] ?? ResourceVersionScanStatus::Pending->value)),
            scanResult: is_array($data['scan_result'] ?? null) ? $data['scan_result'] : [],
            releaseNotes: self::nullableString($data['release_notes'] ?? null),
            createdBy: max(0, (int) ($data['created_by'] ?? 0)),
            approvedBy: isset($data['approved_by']) ? max(0, (int) $data['approved_by']) : null,
            activatedAt: self::nullableString($data['activated_at'] ?? null),
            suspendedAt: self::nullableString($data['suspended_at'] ?? null),
            createdAt: trim((string) ($data['created_at'] ?? '')),
            updatedAt: trim((string) ($data['updated_at'] ?? '')),
        );
    }

    public function activated(int $approvedBy, string $now): self
    {
        return new self(
            id: $this->id,
            resourceId: $this->resourceId,
            versionLabel: $this->versionLabel,
            status: ResourceVersionStatus::Active,
            isCurrent: true,
            storageProvider: $this->storageProvider,
            storageBucket: $this->storageBucket,
            storageKey: $this->storageKey,
            originalFilename: $this->originalFilename,
            mimeType: $this->mimeType,
            fileSize: $this->fileSize,
            sha256: $this->sha256,
            compatibility: $this->compatibility,
            scanStatus: $this->scanStatus,
            scanResult: $this->scanResult,
            releaseNotes: $this->releaseNotes,
            createdBy: $this->createdBy,
            approvedBy: $approvedBy,
            activatedAt: $now,
            suspendedAt: $this->suspendedAt,
            createdAt: $this->createdAt,
            updatedAt: $now,
        );
    }

    public function withoutCurrent(string $now): self
    {
        return new self(
            id: $this->id,
            resourceId: $this->resourceId,
            versionLabel: $this->versionLabel,
            status: $this->status,
            isCurrent: false,
            storageProvider: $this->storageProvider,
            storageBucket: $this->storageBucket,
            storageKey: $this->storageKey,
            originalFilename: $this->originalFilename,
            mimeType: $this->mimeType,
            fileSize: $this->fileSize,
            sha256: $this->sha256,
            compatibility: $this->compatibility,
            scanStatus: $this->scanStatus,
            scanResult: $this->scanResult,
            releaseNotes: $this->releaseNotes,
            createdBy: $this->createdBy,
            approvedBy: $this->approvedBy,
            activatedAt: $this->activatedAt,
            suspendedAt: $this->suspendedAt,
            createdAt: $this->createdAt,
            updatedAt: $now,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string === '' ? null : $string;
    }

    private static function normalizeSha256(mixed $value): ?string
    {
        $string = strtolower(trim((string) ($value ?? '')));

        return $string === '' ? null : $string;
    }
}
