<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Reports;

final readonly class ReportMetric
{
    public function __construct(
        public string $key,
        public int|float $value,
        public string $unit,
    ) {
        if ($key === '' || $unit === '') {
            throw new ReportException('invalid_metric', 'Report metric key and unit are required.');
        }
    }
}
