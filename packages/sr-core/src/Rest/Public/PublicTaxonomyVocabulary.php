<?php
declare(strict_types=1);

namespace StockResource\Core\Rest\Public;

use StockResource\Core\Content\Taxonomy\ControlledVocabulary;
use StockResource\Core\Content\Taxonomy\TaxonomyCatalog;

final readonly class PublicTaxonomyVocabulary
{
    /**
     * @param array<string, array<string, mixed>> $taxonomies
     */
    private function __construct(private array $taxonomies)
    {
    }

    public static function fromCatalog(TaxonomyCatalog $catalog, ControlledVocabulary $vocabulary): self
    {
        $taxonomies = [];
        foreach ($catalog->definitions() as $taxonomy => $definition) {
            if (! $definition->showInRest()) {
                continue;
            }

            $terms = $vocabulary->termsFor($taxonomy);
            $taxonomies[$taxonomy] = [
                'taxonomy' => $taxonomy,
                'rest_key' => $definition->restKey(),
                'hierarchical' => $definition->hierarchical(),
                'terms' => array_map(static fn(array $term): array => [
                    'slug' => $term['slug'],
                    'name' => $term['name'],
                ], $terms),
            ];
        }

        return new self($taxonomies);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['data' => $this->taxonomies];
    }
}
