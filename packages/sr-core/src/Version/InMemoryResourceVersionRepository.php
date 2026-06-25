<?php
declare(strict_types=1);

namespace StockResource\Core\Version;

use RuntimeException;

final class InMemoryResourceVersionRepository implements ResourceVersionRepository
{
    /** @var array<int, ResourceVersion> */
    private array $versions = [];

    /** @var list<int> */
    private array $transactionLockLog = [];

    public function create(ResourceVersion $version): void
    {
        if (isset($this->versions[$version->id])) {
            throw new RuntimeException('Resource version records are immutable and cannot be overwritten.');
        }

        if ($version->isCurrent) {
            throw new RuntimeException('Current resource versions must be created through activation.');
        }

        $this->versions[$version->id] = $version;
    }

    public function find(int $id): ?ResourceVersion
    {
        return $this->versions[$id] ?? null;
    }

    public function versionsForResource(int $resourceId): array
    {
        $versions = array_values(array_filter(
            $this->versions,
            static fn(ResourceVersion $version): bool => $version->resourceId === $resourceId,
        ));
        usort($versions, static fn(ResourceVersion $left, ResourceVersion $right): int => $left->id <=> $right->id);

        return $versions;
    }

    public function currentForResource(int $resourceId): ?ResourceVersion
    {
        $current = array_values(array_filter(
            $this->versions,
            static fn(ResourceVersion $version): bool => $version->resourceId === $resourceId && $version->isCurrent,
        ));

        if (count($current) > 1) {
            throw new RuntimeException('More than one current resource version exists for one resource.');
        }

        return $current[0] ?? null;
    }

    public function activateCurrent(int $resourceId, int $versionId, int $approvedBy, string $now): ResourceVersion
    {
        $this->transactionLockLog[] = $resourceId;

        $target = $this->versions[$versionId] ?? null;
        if ($target === null || $target->resourceId !== $resourceId) {
            throw new RuntimeException('Resource version does not belong to the requested resource.');
        }
        if (! in_array($target->status, [ResourceVersionStatus::Review, ResourceVersionStatus::Active], true)) {
            throw new RuntimeException('Only review or active resource versions can be activated.');
        }
        if ($target->scanStatus !== ResourceVersionScanStatus::Clean) {
            throw new RuntimeException('Only clean scanned resource versions can be activated.');
        }

        foreach ($this->versions as $id => $version) {
            if ($version->resourceId === $resourceId && $version->isCurrent) {
                $this->versions[$id] = $version->withoutCurrent($now);
            }
        }

        $activated = $target->activated($approvedBy, $now);
        $this->versions[$versionId] = $activated;

        return $activated;
    }

    /**
     * @return list<int>
     */
    public function transactionLockLog(): array
    {
        return $this->transactionLockLog;
    }
}
