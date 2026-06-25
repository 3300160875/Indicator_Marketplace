<?php
declare(strict_types=1);

namespace StockResource\Core\Content\Taxonomy;

final readonly class TermDeletionGuard
{
    /**
     * @param array<string, int> $references keyed by taxonomy:slug
     */
    public function __construct(private array $references)
    {
    }

    public function canDelete(string $taxonomy, string $slug): bool
    {
        return ($this->references[$this->key($taxonomy, $slug)] ?? 0) === 0;
    }

    public function deletionError(string $taxonomy, string $slug): ?string
    {
        return $this->canDelete($taxonomy, $slug) ? null : 'referenced_term_requires_migration';
    }

    private function key(string $taxonomy, string $slug): string
    {
        return $taxonomy . ':' . ControlledVocabulary::normalizeSlug($slug);
    }
}
