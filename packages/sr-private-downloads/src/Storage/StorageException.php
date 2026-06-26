<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Storage;

use RuntimeException;

final class StorageException extends RuntimeException
{
    public function __construct(
        public readonly string $codeName,
        string $message,
        public readonly int $statusCode = 0,
    ) {
        parent::__construct($message);
    }

    public static function invalidKey(string $key): self
    {
        return new self('invalid_key', 'Invalid storage object key: '.$key);
    }

    public static function invalidAcl(string $visibility): self
    {
        return new self('invalid_acl', 'Only private object ACL is allowed: '.$visibility);
    }

    public static function notFound(string $key): self
    {
        return new self('not_found', 'Storage object not found: '.$key, 404);
    }

    public static function accessDenied(string $key): self
    {
        return new self('access_denied', 'Anonymous access denied for private object: '.$key, 403);
    }

    public static function unavailable(string $message, int $statusCode = 0): self
    {
        return new self('storage_unavailable', $message, $statusCode);
    }
}
