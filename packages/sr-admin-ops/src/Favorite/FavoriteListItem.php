<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Favorite;

final readonly class FavoriteListItem
{
    public function __construct(
        public int $favoriteId,
        public int $userId,
        public int $resourceId,
        public string $favoritedAt,
        public ?FavoriteResourceSnapshot $resource,
        public bool $resourceUnavailable,
    ) {
    }

    public static function fromRecord(FavoriteRecord $record, ?FavoriteResourceSnapshot $resource): self
    {
        $resourceUnavailable = $resource === null || ! $resource->isVisible();

        return new self(
            favoriteId: $record->id,
            userId: $record->userId,
            resourceId: $record->resourceId,
            favoritedAt: $record->createdAt,
            resource: $resourceUnavailable ? null : $resource,
            resourceUnavailable: $resourceUnavailable,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toCustomerPayload(): array
    {
        return [
            'favorite_id' => $this->favoriteId,
            'resource_id' => $this->resourceId,
            'favorited_at' => $this->favoritedAt,
            'resource' => $this->resource?->publicPayload(),
            'resource_unavailable' => $this->resourceUnavailable,
            'unavailable_reason' => $this->resourceUnavailable ? 'resource_unavailable' : null,
        ];
    }
}
