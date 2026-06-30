<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Rights;

final readonly class RightsEvidencePolicy
{
    /** @param list<string> $privatePrefixes */
    public function __construct(private array $privatePrefixes = ['private/rights/', 'sr-private/rights/'])
    {
    }

    public function isPrivateStorageKey(?string $storageKey): bool
    {
        if ($storageKey === null) {
            return false;
        }

        $storageKey = trim($storageKey);
        if ($storageKey === '') {
            return false;
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

    public function assertPrivateStorageKey(?string $storageKey): void
    {
        if (! $this->isPrivateStorageKey($storageKey)) {
            throw new RightsException('evidence_storage_not_private', 'Rights evidence must be stored under a private storage key.');
        }
    }
}
