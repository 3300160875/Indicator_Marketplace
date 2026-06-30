<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Reports;

final readonly class MetricDefinition
{
    public function __construct(
        public string $key,
        public string $value,
    ) {
        if ($key === '') {
            throw new ReportException('invalid_metric_definition', 'Metric definition key is required.');
        }
    }
}
