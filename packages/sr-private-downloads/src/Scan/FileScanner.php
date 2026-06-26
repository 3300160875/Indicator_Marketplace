<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Scan;

use StockResource\PrivateDownloads\Storage\StorageObjectKey;

interface FileScanner
{
    public function scan(StorageObjectKey $key, string $sha256, string $mimeType, int $size): ScanResult;
}
