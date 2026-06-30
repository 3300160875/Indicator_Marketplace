<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Favorite;

final readonly class FavoriteToggleResult
{
    /**
     * @param list<string> $cacheKeysToInvalidate
     */
    public function __construct(
        public string $action,
        public bool $favorited,
        public ?FavoriteRecord $record,
        public array $cacheKeysToInvalidate,
    ) {
        if (! in_array($action, ['added', 'already_present', 'removed', 'already_absent'], true)) {
            throw new FavoriteException('invalid_favorite_action', 'Favorite action is unsupported.');
        }
    }
}
