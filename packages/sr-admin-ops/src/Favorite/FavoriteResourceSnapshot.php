<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Favorite;

final readonly class FavoriteResourceSnapshot
{
    private const VISIBLE_STATUSES = ['publish'];

    public function __construct(
        public int $id,
        public string $status,
        public string $slug,
        public string $title,
        public string $excerpt,
        public string $accessMode,
    ) {
        if ($id <= 0) {
            throw new FavoriteException('invalid_resource_id', 'Resource ID must be positive.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            status: trim((string) ($data['status'] ?? '')),
            slug: trim((string) ($data['slug'] ?? '')),
            title: trim((string) ($data['title'] ?? '')),
            excerpt: trim((string) ($data['excerpt'] ?? '')),
            accessMode: trim((string) ($data['access_mode'] ?? '')),
        );
    }

    public function isVisible(): bool
    {
        return in_array($this->status, self::VISIBLE_STATUSES, true) && $this->accessMode !== 'unavailable';
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        if (! $this->isVisible()) {
            throw new FavoriteException('resource_unavailable', 'Unavailable resources cannot be projected.');
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'access_mode' => $this->accessMode,
        ];
    }
}
