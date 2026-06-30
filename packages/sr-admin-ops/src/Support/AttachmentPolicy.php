<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Support;

final readonly class AttachmentPolicy
{
    /** @param list<string> $privatePrefixes */
    public function __construct(private array $privatePrefixes = ['private/support/', 'sr-private/support/'])
    {
    }

    public function isPrivateStorageKey(?string $storageKey): bool
    {
        if ($storageKey === null) {
            return true;
        }

        $storageKey = trim($storageKey);
        if ($storageKey === '') {
            return true;
        }
        if (str_contains($storageKey, '..') || str_starts_with($storageKey, '/')) {
            return false;
        }
        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $storageKey) === 1) {
            return false;
        }

        foreach ($this->privatePrefixes as $prefix) {
            if (str_starts_with($storageKey, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function assertPrivate(?string $storageKey): void
    {
        if (! $this->isPrivateStorageKey($storageKey)) {
            throw new SupportException('attachment_not_private', 'Support attachments must use private storage keys.');
        }
    }
}
