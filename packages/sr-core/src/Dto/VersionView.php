<?php
declare(strict_types=1);

namespace StockResource\Core\Dto;

use StockResource\Core\Version\ResourceVersion;

final readonly class VersionView
{
    /**
     * @param array<string, mixed> $compatibility
     */
    public function __construct(
        public int $id,
        public int $resourceId,
        public string $versionLabel,
        public string $status,
        public string $scanStatus,
        public ?int $fileSize,
        public array $compatibility,
        public ?string $releaseNotes,
        public ?string $activatedAt,
    ) {
    }

    public static function fromResourceVersion(ResourceVersion $version): self
    {
        return new self(
            id: $version->id,
            resourceId: $version->resourceId,
            versionLabel: $version->versionLabel,
            status: $version->status->value,
            scanStatus: $version->scanStatus->value,
            fileSize: $version->fileSize,
            compatibility: $version->compatibility,
            releaseNotes: $version->releaseNotes,
            activatedAt: $version->activatedAt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'resource_id' => $this->resourceId,
            'version_label' => $this->versionLabel,
            'status' => $this->status,
            'scan_status' => $this->scanStatus,
            'file_size' => $this->fileSize,
            'compatibility' => $this->compatibility,
            'release_notes' => $this->releaseNotes,
            'activated_at' => $this->activatedAt,
        ];
    }
}
