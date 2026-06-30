<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Reports;

final readonly class ReportFacts
{
    /**
     * @param list<array<string, mixed>> $orders
     * @param list<array<string, mixed>> $downloads
     * @param list<array<string, mixed>> $reviews
     * @param array<string, mixed> $health
     */
    public function __construct(
        public array $orders,
        public array $downloads,
        public array $reviews,
        public array $health,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $orders
     * @param list<array<string, mixed>> $downloads
     * @param list<array<string, mixed>> $reviews
     * @param array<string, mixed> $health
     */
    public static function fromArrays(array $orders, array $downloads, array $reviews, array $health): self
    {
        return new self(
            array_values($orders),
            array_values($downloads),
            array_values($reviews),
            $health,
        );
    }

    public function estimatedRows(): int
    {
        return count($this->orders) + count($this->downloads) + count($this->reviews);
    }
}
