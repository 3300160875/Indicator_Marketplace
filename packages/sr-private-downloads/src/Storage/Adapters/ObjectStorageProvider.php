<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Storage\Adapters;

enum ObjectStorageProvider: string
{
    case S3 = 's3';
    case COS = 'cos';
    case OSS = 'oss';
    case MINIO = 'minio';

    public function usesPathStyleByDefault(): bool
    {
        return $this === self::MINIO;
    }
}
