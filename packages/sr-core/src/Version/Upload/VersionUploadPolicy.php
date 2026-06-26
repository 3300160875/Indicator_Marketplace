<?php

declare(strict_types=1);

namespace StockResource\Core\Version\Upload;

final readonly class VersionUploadPolicy
{
    /**
     * @param  list<string>  $allowedMimeTypes
     */
    public function __construct(
        public int $maxBytes,
        public array $allowedMimeTypes,
        public int $maxArchiveEntries,
        public int $maxArchiveDepth,
        public int $maxExpandedBytes,
        public float $maxCompressionRatio,
    ) {}

    public function assertAccepts(UploadedVersionFile $file): string
    {
        if ($file->size() <= 0) {
            throw VersionUploadException::invalidUpload('Upload file cannot be empty.');
        }

        if ($file->size() > $this->maxBytes) {
            throw VersionUploadException::fileTooLarge($file->size(), $this->maxBytes);
        }

        $mimeType = $this->sniffMimeType($file);
        if (! in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw VersionUploadException::invalidMime($mimeType);
        }

        if ($file->archiveEntryCount < 0 || $file->archiveEntryCount > $this->maxArchiveEntries) {
            throw VersionUploadException::archiveLimitExceeded('entry_count');
        }

        if ($file->archiveMaxDepth < 0 || $file->archiveMaxDepth > $this->maxArchiveDepth) {
            throw VersionUploadException::archiveLimitExceeded('max_depth');
        }

        $expandedBytes = $file->archiveExpandedBytes > 0 ? $file->archiveExpandedBytes : $file->size();
        if ($expandedBytes > $this->maxExpandedBytes) {
            throw VersionUploadException::archiveLimitExceeded('expanded_bytes');
        }

        $ratio = $expandedBytes / max(1, $file->size());
        if ($ratio > $this->maxCompressionRatio) {
            throw VersionUploadException::compressionRatioExceeded($ratio, $this->maxCompressionRatio);
        }

        return $mimeType;
    }

    private function sniffMimeType(UploadedVersionFile $file): string
    {
        if (str_starts_with($file->contents, "PK\x03\x04")) {
            return 'application/zip';
        }

        if (str_starts_with(ltrim($file->contents), '<?php')) {
            return 'application/x-php';
        }

        return trim($file->clientMimeType) ?: 'application/octet-stream';
    }
}
