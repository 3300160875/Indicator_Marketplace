<?php
declare(strict_types=1);

namespace StockResource\Core\Content\Taxonomy;

final readonly class TaxonomyRestSchema
{
    /**
     * @return array{taxonomy: string, slug: string, name: string, count: int}
     */
    public function term(string $taxonomy, string $slug, string $name, int $count): array
    {
        return [
            'taxonomy' => $taxonomy,
            'slug' => ControlledVocabulary::normalizeSlug($slug),
            'name' => $name,
            'count' => max(0, $count),
        ];
    }
}
