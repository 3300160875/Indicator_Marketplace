<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Storage;

final readonly class StorageObjectKey
{
    private function __construct(public string $value) {}

    public static function fromString(string $key): self
    {
        $key = trim($key);
        if (
            $key === ''
            || str_starts_with($key, '/')
            || str_contains($key, '..')
            || preg_match('/[\x00-\x1F\x7F]/', $key)
            || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', $key)
        ) {
            throw StorageException::invalidKey($key);
        }

        return new self($key);
    }

    public function encodedPath(): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $this->value)));
    }
}
