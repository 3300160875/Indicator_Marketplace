<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Reports;

final readonly class ReportQueryPlan
{
    public function __construct(
        public int $estimatedRowsScanned,
        public bool $foregroundSafe,
        public int $maxForegroundRows,
        public int $maxForegroundDays,
    ) {
    }
}
