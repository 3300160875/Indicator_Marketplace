<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Storage\Adapters;

use InvalidArgumentException;
use StockResource\PrivateDownloads\Storage\StorageService;

final class ProductionStorageAdapterFactory
{
    public static function create(S3CompatibleAdapterConfig $config, HttpTransport $transport): StorageService
    {
        return new MinioStorageAdapter(
            endpoint: self::endpointForAdapter($config),
            region: $config->region,
            bucket: $config->bucket,
            accessKey: $config->accessKey,
            secretKey: $config->secretKey,
            transport: $transport,
            pathStyle: $config->pathStyle,
        );
    }

    private static function endpointForAdapter(S3CompatibleAdapterConfig $config): string
    {
        if ($config->pathStyle) {
            return $config->endpoint;
        }

        $parts = parse_url($config->endpoint);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('Storage endpoint must include scheme and host.');
        }

        $authority = $config->bucket.'.'.$parts['host'];
        if (isset($parts['port'])) {
            $authority .= ':'.$parts['port'];
        }

        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

        return $parts['scheme'].'://'.$authority.$path;
    }
}
