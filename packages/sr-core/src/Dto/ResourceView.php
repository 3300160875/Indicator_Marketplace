<?php
declare(strict_types=1);

namespace StockResource\Core\Dto;

final readonly class ResourceView
{
    /**
     * @param array<string, list<string>> $taxonomies
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public string $excerpt,
        public string $content,
        public string $accessMode,
        public array $taxonomies,
        public array $meta,
        public VersionView $currentVersion,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'access_mode' => $this->accessMode,
            'taxonomies' => $this->taxonomies,
            'meta' => $this->meta,
            'current_version' => $this->currentVersion->toArray(),
        ];
    }
}
