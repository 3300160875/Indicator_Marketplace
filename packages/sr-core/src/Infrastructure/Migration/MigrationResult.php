<?php
declare(strict_types=1);

namespace StockResource\Core\Infrastructure\Migration;

final readonly class MigrationResult
{
    /**
     * @param list<array{version: string, checksum: string, description: string}> $planned
     * @param list<string> $applied
     * @param list<array{version: string, message: string}> $failures
     */
    public function __construct(
        private array $planned,
        private array $applied,
        private array $failures,
    ) {
    }

    /**
     * @return list<array{version: string, checksum: string, description: string}>
     */
    public function planned(): array
    {
        return $this->planned;
    }

    /**
     * @return list<string>
     */
    public function applied(): array
    {
        return $this->applied;
    }

    /**
     * @return list<array{version: string, message: string}>
     */
    public function failures(): array
    {
        return $this->failures;
    }
}
