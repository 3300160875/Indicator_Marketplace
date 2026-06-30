<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Reports;

final readonly class BusinessReport
{
    /**
     * @param array<string, ReportMetric> $metrics
     * @param array<string, MetricDefinition> $definitions
     */
    public function __construct(
        public ReportingWindow $window,
        public array $metrics,
        public array $definitions,
        public ReportQueryPlan $queryPlan,
    ) {
    }

    public function metric(string $key): ReportMetric
    {
        if (! isset($this->metrics[$key])) {
            throw new ReportException('metric_not_found', 'Report metric is unavailable.');
        }

        return $this->metrics[$key];
    }
}
