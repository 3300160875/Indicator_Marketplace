<?php
declare(strict_types=1);

namespace StockResource\Core\Admin\ResourceEditor;

final readonly class ResourceDraft
{
    /**
     * @param array<string, list<string>> $taxonomies
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public int $resourceId,
        public string $title,
        public string $excerpt,
        public string $content,
        public int $screenshotCount,
        public bool $priceConfigured,
        public array $taxonomies,
        public array $meta,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            resourceId: max(0, (int) ($data['resource_id'] ?? 0)),
            title: trim((string) ($data['post_title'] ?? '')),
            excerpt: trim((string) ($data['post_excerpt'] ?? '')),
            content: trim((string) ($data['post_content'] ?? '')),
            screenshotCount: max(0, (int) ($data['screenshot_count'] ?? 0)),
            priceConfigured: (bool) ($data['price_configured'] ?? false),
            taxonomies: is_array($data['taxonomies'] ?? null) ? $data['taxonomies'] : [],
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
        );
    }

    public function meta(string $key): mixed
    {
        return $this->meta[$key] ?? null;
    }

    public function taxonomySelected(string $taxonomy): bool
    {
        $values = $this->taxonomies[$taxonomy] ?? [];

        return is_array($values) && $values !== [];
    }
}
