<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Favorite;

final class InMemoryFavoriteRepository implements FavoriteRepository
{
    /** @var array<string, FavoriteRecord> */
    private array $records = [];
    private int $nextId = 1;

    public function findByUserAndResource(int $userId, int $resourceId): ?FavoriteRecord
    {
        $this->assertIds($userId, $resourceId);

        return $this->records[$this->key($userId, $resourceId)] ?? null;
    }

    public function addIfMissing(int $userId, int $resourceId, string $createdAt): FavoriteRecord
    {
        $this->assertIds($userId, $resourceId);
        $key = $this->key($userId, $resourceId);
        if (isset($this->records[$key])) {
            return $this->records[$key];
        }

        $record = new FavoriteRecord(
            id: $this->nextId++,
            userId: $userId,
            resourceId: $resourceId,
            createdAt: $createdAt,
        );
        $this->records[$key] = $record;

        return $record;
    }

    public function deleteIfPresent(int $userId, int $resourceId): ?FavoriteRecord
    {
        $this->assertIds($userId, $resourceId);
        $key = $this->key($userId, $resourceId);
        $record = $this->records[$key] ?? null;
        unset($this->records[$key]);

        return $record;
    }

    public function listForUser(int $userId): array
    {
        if ($userId <= 0) {
            throw new FavoriteException('invalid_user_id', 'Favorite user ID must be positive.');
        }

        $records = array_values(array_filter(
            $this->records,
            static fn (FavoriteRecord $record): bool => $record->userId === $userId,
        ));
        usort($records, static function (FavoriteRecord $left, FavoriteRecord $right): int {
            return strcmp($left->createdAt, $right->createdAt) ?: $left->id <=> $right->id;
        });

        return $records;
    }

    public function countForUser(int $userId): int
    {
        return count($this->listForUser($userId));
    }

    private function key(int $userId, int $resourceId): string
    {
        return $userId.':'.$resourceId;
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
