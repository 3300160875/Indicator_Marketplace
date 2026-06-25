<?php
declare(strict_types=1);

namespace StockResource\Core\Admin\ResourceEditor;

final readonly class EditorSection
{
    /**
     * @param list<string> $fields
     */
    public function __construct(
        private string $key,
        private string $label,
        private array $fields,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * @return list<string>
     */
    public function fields(): array
    {
        return $this->fields;
    }
}
