<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Storage\Adapters;

use InvalidArgumentException;

final readonly class S3CompatibleAdapterConfig
{
    private function __construct(
        public ObjectStorageProvider $provider,
        public string $endpoint,
        public string $region,
        public string $bucket,
        public string $accessKey,
        public string $secretKey,
        public bool $pathStyle,
    ) {}

    public static function forProvider(
        ObjectStorageProvider $provider,
        string $endpoint,
        string $region,
        string $bucket,
        string $accessKey,
        string $secretKey,
        ?bool $pathStyle = null,
    ): self {
        return new self(
            provider: $provider,
            endpoint: self::normalizeEndpoint($endpoint),
            region: self::nonEmpty($region, 'region'),
            bucket: self::nonEmpty($bucket, 'bucket'),
            accessKey: self::nonEmpty($accessKey, 'accessKey'),
            secretKey: self::nonEmpty($secretKey, 'secretKey'),
            pathStyle: $pathStyle ?? $provider->usesPathStyleByDefault(),
        );
    }

    private static function normalizeEndpoint(string $endpoint): string
    {
        $endpoint = rtrim(self::nonEmpty($endpoint, 'endpoint'), '/');
        $parts = parse_url($endpoint);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('Storage endpoint must include scheme and host.');
        }

        if (! in_array($parts['scheme'], ['http', 'https'], true)) {
            throw new InvalidArgumentException('Storage endpoint must use http or https.');
        }

        return $endpoint;
    }

    private static function nonEmpty(string $value, string $name): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException('Storage '.$name.' cannot be empty.');
        }

        return $value;
    }
}
