<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Scan;

use StockResource\PrivateDownloads\Storage\StorageObjectKey;

final class RecordingFileScanner implements FileScanner
{
    /** @var list<array{key: string, sha256: string, mime_type: string, size: int}> */
    public array $scans = [];

    public function __construct(private readonly ScanResult $result) {}

    public function scan(StorageObjectKey $key, string $sha256, string $mimeType, int $size): ScanResult
    {
        $this->scans[] = [
            'key' => $key->value,
            'sha256' => $sha256,
            'mime_type' => $mimeType,
            'size' => $size,
        ];

        return $this->result;
    }
}
