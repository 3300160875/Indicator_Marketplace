<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Reports;

final readonly class HealthCheckResult
{
    /**
     * @param array<string, object{status:string, reason:string, value:int|string|null}> $checks
     */
    public function __construct(
        public string $status,
        public array $checks,
    ) {
    }
}
