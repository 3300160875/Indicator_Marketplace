<?php
declare(strict_types=1);

namespace StockResource\Platform\Admin;

use StockResource\Platform\Dependency\DependencyReport;
use StockResource\Platform\Runtime\Runtime;

final readonly class AdminNoticeRenderer
{
    public function __construct(private Runtime $runtime)
    {
    }

    public function render(DependencyReport $report): void
    {
        $message = 'Stock Resource platform is not active. ' . implode(' ', $report->failures());
        $this->runtime->adminNotice('error', $message);
    }
}
