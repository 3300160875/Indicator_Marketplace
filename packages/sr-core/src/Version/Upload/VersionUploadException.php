<?php

declare(strict_types=1);

namespace StockResource\Core\Version\Upload;

use RuntimeException;

final class VersionUploadException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }

    public static function invalidMime(string $mimeType): self
    {
        return new self('invalid_mime', 'Unsupported upload MIME type: '.$mimeType);
    }

    public static function fileTooLarge(int $size, int $maxBytes): self
    {
        return new self('file_too_large', 'Upload size '.$size.' exceeds '.$maxBytes.' bytes.');
    }

    public static function archiveLimitExceeded(string $limit): self
    {
        return new self('archive_limit_exceeded', 'Archive limit exceeded: '.$limit);
    }

    public static function compressionRatioExceeded(float $ratio, float $maxRatio): self
    {
        return new self('compression_ratio_exceeded', 'Archive compression ratio '.$ratio.' exceeds '.$maxRatio.'.');
    }

    public static function invalidUpload(string $reason): self
    {
        return new self('invalid_upload', $reason);
    }
}
