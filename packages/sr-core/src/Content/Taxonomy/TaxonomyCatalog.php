<?php
declare(strict_types=1);

namespace StockResource\Core\Content\Taxonomy;

final readonly class TaxonomyCatalog
{
    /** @param array<string, TaxonomyDefinition> $definitions */
    private function __construct(private array $definitions)
    {
    }

    public static function defaults(): self
    {
        return new self([
            'download_category' => new TaxonomyDefinition(
                name: 'download_category',
                restKey: 'download_category',
                rewritePattern: '/category/{slug}/',
                hierarchical: true,
                showUi: true,
                showInRest: true,
                labels: ['name' => 'Resource Categories', 'singular_name' => 'Resource Category'],
            ),
            'sr_platform' => new TaxonomyDefinition(
                name: 'sr_platform',
                restKey: 'platform',
                rewritePattern: '/platform/{slug}/',
                hierarchical: false,
                showUi: true,
                showInRest: true,
                labels: ['name' => 'Platforms', 'singular_name' => 'Platform'],
            ),
            'sr_indicator_type' => new TaxonomyDefinition(
                name: 'sr_indicator_type',
                restKey: 'indicator_type',
                rewritePattern: '/indicator-type/{slug}/',
                hierarchical: false,
                showUi: true,
                showInRest: true,
                labels: ['name' => 'Indicator Types', 'singular_name' => 'Indicator Type'],
            ),
            'sr_strategy_tag' => new TaxonomyDefinition(
                name: 'sr_strategy_tag',
                restKey: 'strategy_tag',
                rewritePattern: '/strategy/{slug}/',
                hierarchical: false,
                showUi: true,
                showInRest: true,
                labels: ['name' => 'Strategy Tags', 'singular_name' => 'Strategy Tag'],
            ),
            'sr_content_type' => new TaxonomyDefinition(
                name: 'sr_content_type',
                restKey: 'content_type',
                rewritePattern: '/content-type/{slug}/',
                hierarchical: false,
                showUi: true,
                showInRest: true,
                labels: ['name' => 'Content Types', 'singular_name' => 'Content Type'],
            ),
        ]);
    }

    /**
     * @return array<string, TaxonomyDefinition>
     */
    public function definitions(): array
    {
        return $this->definitions;
    }
}
