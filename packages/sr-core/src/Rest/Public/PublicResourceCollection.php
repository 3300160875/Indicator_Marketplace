<?php
declare(strict_types=1);

namespace StockResource\Core\Rest\Public;

use StockResource\Core\Dto\ResourceView;

final readonly class PublicResourceCollection
{
    /** @param list<ResourceView> $resources */
    private function __construct(private array $resources)
    {
    }

    /**
     * @param list<?ResourceView> $views
     */
    public static function fromViews(array $views): self
    {
        return new self(array_values(array_filter(
            $views,
            static fn(?ResourceView $view): bool => $view instanceof ResourceView,
        )));
    }

    /**
     * @return array<string, mixed>
     */
    public function list(PublicResourceQuery $query): array
    {
        $filtered = array_values(array_filter(
            $this->resources,
            static fn(ResourceView $view): bool => self::matches($view, $query),
        ));

        self::sort($filtered, $query->sort());

        $total = count($filtered);
        $offset = ($query->page() - 1) * $query->perPage();
        $pageItems = array_slice($filtered, $offset, $query->perPage());

        return [
            'data' => array_map(static fn(ResourceView $view): array => $view->toArray(), $pageItems),
            'pagination' => [
                'page' => $query->page(),
                'per_page' => $query->perPage(),
                'total' => $total,
                'total_pages' => $total === 0 ? 0 : (int) ceil($total / $query->perPage()),
            ],
            'canonical_query' => $query->canonicalQueryString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(string|int $idOrSlug): array
    {
        $needle = trim((string) $idOrSlug);
        foreach ($this->resources as $view) {
            if ((string) $view->id === $needle || $view->slug === $needle) {
                return ['data' => $view->toArray()];
            }
        }

        throw PublicRestError::resourceUnavailable($needle);
    }

    private static function matches(ResourceView $view, PublicResourceQuery $query): bool
    {
        foreach ($query->filters() as $restKey => $slug) {
            $taxonomy = match ($restKey) {
                'category' => 'download_category',
                'platform' => 'sr_platform',
                'indicator_type' => 'sr_indicator_type',
                'strategy_tag' => 'sr_strategy_tag',
                'content_type' => 'sr_content_type',
                default => $restKey,
            };
            if (! in_array($slug, $view->taxonomies[$taxonomy] ?? [], true)) {
                return false;
            }
        }

        $search = $query->search();
        if ($search !== null) {
            $haystack = strtolower($view->title . ' ' . $view->excerpt . ' ' . $view->content);
            if (! str_contains($haystack, strtolower($search))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<ResourceView> $resources
     */
    private static function sort(array &$resources, string $sort): void
    {
        usort($resources, static function (ResourceView $a, ResourceView $b) use ($sort): int {
            return match ($sort) {
                'title_asc' => strcasecmp($a->title, $b->title),
                'popular_desc' => $b->id <=> $a->id,
                default => strcmp((string) ($b->currentVersion->activatedAt ?? ''), (string) ($a->currentVersion->activatedAt ?? '')),
            };
        });
    }
}
