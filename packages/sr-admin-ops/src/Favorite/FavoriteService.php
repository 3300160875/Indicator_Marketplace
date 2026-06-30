<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Favorite;

final readonly class FavoriteService
{
    public function __construct(private FavoriteRepository $repository)
    {
    }

    public function addFavorite(int $userId, int $resourceId, string $nowUtc): FavoriteToggleResult
    {
        $this->assertIds($userId, $resourceId);
        $existing = $this->repository->findByUserAndResource($userId, $resourceId);
        if ($existing !== null) {
            return new FavoriteToggleResult(
                action: 'already_present',
                favorited: true,
                record: $existing,
                cacheKeysToInvalidate: FavoriteCacheKeys::invalidationKeys($userId, $resourceId),
            );
        }

        return new FavoriteToggleResult(
            action: 'added',
            favorited: true,
            record: $this->repository->addIfMissing($userId, $resourceId, $nowUtc),
            cacheKeysToInvalidate: FavoriteCacheKeys::invalidationKeys($userId, $resourceId),
        );
    }

    public function removeFavorite(int $userId, int $resourceId): FavoriteToggleResult
    {
        $this->assertIds($userId, $resourceId);
        $deleted = $this->repository->deleteIfPresent($userId, $resourceId);
        if ($deleted === null) {
            return new FavoriteToggleResult(
                action: 'already_absent',
                favorited: false,
                record: null,
                cacheKeysToInvalidate: FavoriteCacheKeys::invalidationKeys($userId, $resourceId),
            );
        }

        return new FavoriteToggleResult(
            action: 'removed',
            favorited: false,
            record: $deleted,
            cacheKeysToInvalidate: FavoriteCacheKeys::invalidationKeys($userId, $resourceId),
        );
    }

    public function setFavorite(int $userId, int $resourceId, bool $favorited, string $nowUtc): FavoriteToggleResult
    {
        return $favorited
            ? $this->addFavorite($userId, $resourceId, $nowUtc)
            : $this->removeFavorite($userId, $resourceId);
    }

    public function isFavorited(int $userId, int $resourceId): bool
    {
        $this->assertIds($userId, $resourceId);

        return $this->repository->findByUserAndResource($userId, $resourceId) !== null;
    }

    /**
     * @param array<int, FavoriteResourceSnapshot> $resourcesById
     * @return list<FavoriteListItem>
     */
    public function listForUser(int $userId, array $resourcesById): array
    {
        if ($userId <= 0) {
            throw new FavoriteException('invalid_user_id', 'Favorite user ID must be positive.');
        }

        return array_values(array_map(
            static fn (FavoriteRecord $record): FavoriteListItem => FavoriteListItem::fromRecord(
                $record,
                $resourcesById[$record->resourceId] ?? null,
            ),
            $this->repository->listForUser($userId),
        ));
    }

    private function assertIds(int $userId, int $resourceId): void
    {
        if ($userId <= 0) {
            throw new FavoriteException('invalid_user_id', 'Favorite user ID must be positive.');
        }
        if ($resourceId <= 0) {
            throw new FavoriteException('invalid_resource_id', 'Favorite resource ID must be positive.');
        }
    }
}
