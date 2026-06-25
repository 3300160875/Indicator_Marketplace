<?php
declare(strict_types=1);

namespace StockResource\Platform\Dependency;

final readonly class DependencyReport
{
    /**
     * @param list<string> $failures
     */
    public function __construct(private array $failures)
    {
    }

    public function passed(): bool
    {
        return $this->failures === [];
    }

    /**
     * @return list<string>
     */
    public function failures(): array
    {
        return $this->failures;
    }
}
