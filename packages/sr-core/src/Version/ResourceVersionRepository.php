<?php
declare(strict_types=1);

namespace StockResource\Core\Version;

interface ResourceVersionRepository
{
    public function create(ResourceVersion $version): void;

    public function find(int $id): ?ResourceVersion;

    /**
     * @return list<ResourceVersion>
     */
    public function versionsForResource(int $resourceId): array;

    public function currentForResource(int $resourceId): ?ResourceVersion;

    public function activateCurrent(int $resourceId, int $versionId, int $approvedBy, string $now): ResourceVersion;
}
