<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Reports;

use StockResource\AdminOps\Auth\UserContext;

final readonly class ReportPermissionPolicy
{
    public function canView(UserContext $user): bool
    {
        return $user->hasCapability('view_sr_reports');
    }

    public function canExport(UserContext $user): bool
    {
        return $user->hasCapability('export_sr_aggregated_reports');
    }
}
