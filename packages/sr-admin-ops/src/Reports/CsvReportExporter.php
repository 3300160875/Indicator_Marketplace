<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Reports;

use StockResource\AdminOps\Auth\UserContext;

final readonly class CsvReportExporter
{
    public function __construct(private ReportPermissionPolicy $permissionPolicy = new ReportPermissionPolicy())
    {
    }

    public function export(UserContext $user, BusinessReport $report): string
    {
        if (! $this->permissionPolicy->canExport($user)) {
            throw new ReportException('report_export_forbidden', 'User cannot export reports.');
        }

        $lines = ['metric,value,unit'];
        foreach ($report->metrics as $metric) {
            $lines[] = $this->csv([$metric->key, (string) $metric->value, $metric->unit]);
        }

        return implode("\n", $lines)."\n";
    }

    /** @param list<string> $fields */
    private function csv(array $fields): string
    {
        return implode(',', array_map(static function (string $field): string {
            if (preg_match('/^[=+\-@]/', $field) === 1) {
                $field = "'".$field;
            }
            if (str_contains($field, ',') || str_contains($field, '"') || str_contains($field, "\n")) {
                return '"'.str_replace('"', '""', $field).'"';
            }

            return $field;
        }, $fields));
    }
}
