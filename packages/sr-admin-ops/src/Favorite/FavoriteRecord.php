<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Favorite;

final readonly class FavoriteRecord
{
    public function __construct(
        public int $id,
        public int $userId,
        public int $resourceId,
        public string $createdAt,
    ) {
        if ($id < 0) {
            throw new FavoriteException('invalid_favorite_id', 'Favorite ID must not be negative.');
        }
        if ($userId <= 0) {
            throw new FavoriteException('invalid_user_id', 'Favorite user ID must be positive.');
        }
        if ($resourceId <= 0) {
            throw new FavoriteException('invalid_resource_id', 'Favorite resource ID must be positive.');
        }
        if (date_create_immutable($createdAt) === false) {
            throw new FavoriteException('invalid_created_at', 'Favorite created_at must be an ISO-8601 datetime.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: max(0, (int) ($data['id'] ?? 0)),
            userId: (int) ($data['user_id'] ?? 0),
            resourceId: (int) ($data['resource_id'] ?? 0),
            createdAt: trim((string) ($data['created_at'] ?? '')),
        );
    }
}
