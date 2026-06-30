<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Favorite;

interface FavoriteRepository
{
    public function findByUserAndResource(int $userId, int $resourceId): ?FavoriteRecord;

    public function addIfMissing(int $userId, int $resourceId, string $createdAt): FavoriteRecord;

    public function deleteIfPresent(int $userId, int $resourceId): ?FavoriteRecord;

    /**
     * @return list<FavoriteRecord>
     */
    public function listForUser(int $userId): array;

    public function countForUser(int $userId): int;
}
