<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Favorite;

final readonly class FavoriteCacheKeys
{
    public static function userList(int $userId): string
    {
        self::assertPositive($userId, 'invalid_user_id');

        return 'favorite:user:'.$userId;
    }

    public static function userResource(int $userId, int $resourceId): string
    {
        self::assertPositive($userId, 'invalid_user_id');
        self::assertPositive($resourceId, 'invalid_resource_id');

        return 'favorite:user:'.$userId.':resource:'.$resourceId;
    }

    /**
     * @return list<string>
     */
    public static function invalidationKeys(int $userId, int $resourceId): array
    {
        return [
            self::userList($userId),
            self::userResource($userId, $resourceId),
        ];
    }

    private static function assertPositive(int $id, string $code): void
    {
        if ($id <= 0) {
            throw new FavoriteException($code, 'Favorite cache key IDs must be positive.');
        }
    }
}
