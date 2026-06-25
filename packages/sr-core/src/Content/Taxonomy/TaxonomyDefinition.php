<?php
declare(strict_types=1);

namespace StockResource\Core\Content\Taxonomy;

final readonly class TaxonomyDefinition
{
    /**
     * @param array<string, string> $labels
     * @param array<string, string> $capabilities
     */
    public function __construct(
        private string $name,
        private string $restKey,
        private string $rewritePattern,
        private bool $hierarchical,
        private bool $showUi,
        private bool $showInRest,
        private array $labels,
        private array $capabilities = [],
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function restKey(): string
    {
        return $this->restKey;
    }

    public function rewritePattern(): string
    {
        return $this->rewritePattern;
    }

    public function hierarchical(): bool
    {
        return $this->hierarchical;
    }

    public function showUi(): bool
    {
        return $this->showUi;
    }

    public function showInRest(): bool
    {
        return $this->showInRest;
    }

    /**
     * @return array<string, mixed>
     */
    public function registrationArgs(): array
    {
        return [
            'hierarchical' => $this->hierarchical,
            'show_ui' => $this->showUi,
            'show_in_rest' => $this->showInRest,
            'rest_base' => $this->restKey,
            'rewrite' => ['slug' => trim(str_replace('{slug}', '', $this->rewritePattern), '/')],
            'labels' => $this->labels,
            'capabilities' => $this->capabilities,
        ];
    }
}
