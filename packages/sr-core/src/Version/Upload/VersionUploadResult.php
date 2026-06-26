<?php

declare(strict_types=1);

namespace StockResource\Core\Version\Upload;

use StockResource\Core\Version\ResourceVersion;
use StockResource\PrivateDownloads\Scan\ScanResult;
use StockResource\PrivateDownloads\Storage\StorageObjectKey;

final readonly class VersionUploadResult
{
    public function __construct(
        public ResourceVersion $version,
        public StorageObjectKey $quarantineKey,
        public StorageObjectKey $finalKey,
        public ScanResult $scanResult,
    ) {}
}
