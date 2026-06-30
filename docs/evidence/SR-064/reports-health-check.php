<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$adminOps = $root.'/packages/sr-admin-ops';

foreach ([
    '/src/Auth/UserContext.php',
    '/src/Reports/BusinessReport.php',
    '/src/Reports/BusinessReportService.php',
    '/src/Reports/CsvReportExporter.php',
    '/src/Reports/HealthCheckResult.php',
    '/src/Reports/HealthCheckService.php',
    '/src/Reports/MetricDefinition.php',
    '/src/Reports/ReportException.php',
    '/src/Reports/ReportFacts.php',
    '/src/Reports/ReportMetric.php',
    '/src/Reports/ReportPermissionPolicy.php',
    '/src/Reports/ReportQueryPlan.php',
    '/src/Reports/ReportingWindow.php',
] as $file) {
    require_once $adminOps.$file;
}

use StockResource\AdminOps\Auth\UserContext;
use StockResource\AdminOps\Reports\BusinessReportService;
use StockResource\AdminOps\Reports\CsvReportExporter;
use StockResource\AdminOps\Reports\HealthCheckService;
use StockResource\AdminOps\Reports\ReportException;
use StockResource\AdminOps\Reports\ReportFacts;
use StockResource\AdminOps\Reports\ReportingWindow;

function sr064_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr064_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$viewer = new UserContext(701, ['sr_operations_analyst'], ['view_sr_reports']);
$legacyViewer = new UserContext(702, ['sr_finance_operator'], ['sr_view_revenue_reports', 'sr_export_finance_reports']);
$exporter = new UserContext(703, ['sr_operations_analyst'], ['view_sr_reports', 'export_sr_aggregated_reports']);
$outsider = new UserContext(704, ['customer'], ['read']);

$window = ReportingWindow::fromLocalDates(
    '2026-06-01',
    '2026-06-30',
    'Asia/Shanghai',
    freshnessDelayMinutes: 15,
);
sr064_same('Asia/Shanghai', $window->timezone, 'reporting timezone is documented');
sr064_same(15, $window->freshnessDelayMinutes, 'freshness delay is documented');

$facts = ReportFacts::fromArrays(
    orders: [
        ['order_id' => 99, 'completed_at' => '2026-05-01T01:00:00+08:00', 'entitlement_created' => false, 'amount_cents' => 9900, 'customer_email' => 'old@example.test'],
        ['order_id' => 101, 'completed_at' => '2026-06-01T01:00:00+08:00', 'entitlement_created' => true, 'amount_cents' => 9900, 'customer_email' => 'a@example.test'],
        ['order_id' => 102, 'completed_at' => '2026-06-02T01:00:00+08:00', 'entitlement_created' => false, 'amount_cents' => 19900, 'customer_email' => 'b@example.test'],
        ['order_id' => 103, 'completed_at' => '2026-06-03T01:00:00+08:00', 'entitlement_created' => true, 'amount_cents' => 29900, 'customer_email' => 'c@example.test'],
    ],
    downloads: [
        ['event_id' => 199, 'occurred_at' => '2026-05-01T02:00:00+08:00', 'status' => 'failed', 'failure_code' => 'old_failure'],
        ['event_id' => 201, 'occurred_at' => '2026-06-01T02:00:00+08:00', 'status' => 'redirected'],
        ['event_id' => 202, 'occurred_at' => '2026-06-01T03:00:00+08:00', 'status' => 'failed', 'failure_code' => 'storage_missing'],
        ['event_id' => 203, 'occurred_at' => '2026-06-01T04:00:00+08:00', 'status' => 'redirected'],
        ['event_id' => 204, 'occurred_at' => '2026-06-01T05:00:00+08:00', 'status' => 'redirected'],
    ],
    reviews: [
        ['submission_id' => 299, 'submitted_at' => '2026-05-01T10:00:00+08:00', 'decided_at' => '2026-05-01T20:00:00+08:00'],
        ['submission_id' => 301, 'submitted_at' => '2026-06-01T10:00:00+08:00', 'decided_at' => '2026-06-01T10:30:00+08:00'],
        ['submission_id' => 302, 'submitted_at' => '2026-06-01T11:00:00+08:00', 'decided_at' => '2026-06-01T12:30:00+08:00'],
        ['submission_id' => 303, 'submitted_at' => '2026-06-01T13:00:00+08:00', 'decided_at' => null],
    ],
    health: [
        'outbox_pending' => 2,
        'outbox_dead_letters' => 1,
        'download_settlement_backlog' => 3,
        'audit_log_latest_at' => '2026-06-30T12:00:00+08:00',
        'now' => '2026-06-30T12:20:00+08:00',
    ],
);
$before = serialize($facts);

$service = new BusinessReportService(maxForegroundRows: 100);
$report = $service->build($viewer, $window, $facts);
sr064_same($before, serialize($facts), 'report build is read-only and does not mutate facts');
sr064_same('orders_without_entitlement', $report->metric('orders_without_entitlement')->key, 'orders without entitlement metric exists');
sr064_same(1, $report->metric('orders_without_entitlement')->value, 'one completed order lacks entitlement');
sr064_same(25.0, $report->metric('download_failure_rate')->value, 'download failure rate is visible as percent');
sr064_same(60.0, $report->metric('average_review_minutes')->value, 'average review duration is visible');
sr064_same(13, $report->queryPlan->estimatedRowsScanned, 'query plan records raw representative row count before window filtering');
sr064_same('Asia/Shanghai', $report->definitions['reporting_timezone']->value, 'metric definitions include timezone');
sr064_same('15 minute freshness delay; reports are not real-time.', $report->definitions['freshness']->value, 'freshness definition is explicit');
sr064_same(true, $report->queryPlan->foregroundSafe, 'representative report is safe for foreground query');

try {
    $service->build($legacyViewer, $window, $facts);
    throw new RuntimeException('legacy finance capability should not view SR-064 reports');
} catch (ReportException $exception) {
    sr064_same('report_view_forbidden', $exception->code(), 'legacy finance report capability is not accepted');
}

try {
    $service->build($outsider, $window, $facts);
    throw new RuntimeException('outsider should not view reports');
} catch (ReportException $exception) {
    sr064_same('report_view_forbidden', $exception->code(), 'report view denial has stable code');
}

try {
    $service->build($viewer, ReportingWindow::fromLocalDates('2026-01-01', '2026-03-15', 'Asia/Shanghai', 15), $facts);
    throw new RuntimeException('oversized window should fail');
} catch (ReportException $exception) {
    sr064_same('report_window_too_large', $exception->code(), 'large foreground report window is rejected');
}

$tooManyFacts = ReportFacts::fromArrays(
    orders: array_map(static fn (int $i): array => ['order_id' => $i, 'completed_at' => '2026-06-01T00:00:00+08:00', 'entitlement_created' => true], range(1, 101)),
    downloads: [],
    reviews: [],
    health: [],
);
try {
    $service->build($viewer, $window, $tooManyFacts);
    throw new RuntimeException('oversized raw facts should fail foreground query');
} catch (ReportException $exception) {
    sr064_same('report_query_too_large', $exception->code(), 'large foreground fact set is rejected');
}

$csv = (new CsvReportExporter())->export($exporter, $report);
sr064_true(str_contains($csv, 'orders_without_entitlement,1,count'), 'CSV export includes aggregate metric');
foreach (['a@example.test', 'order_id', 'private/', 'token', 'signed'] as $forbidden) {
    sr064_true(! str_contains($csv, $forbidden), 'CSV export does not leak '.$forbidden);
}
try {
    (new CsvReportExporter())->export($viewer, $report);
    throw new RuntimeException('viewer without export capability should fail');
} catch (ReportException $exception) {
    sr064_same('report_export_forbidden', $exception->code(), 'export denial has stable code');
}
try {
    (new CsvReportExporter())->export($legacyViewer, $report);
    throw new RuntimeException('legacy finance export capability should fail');
} catch (ReportException $exception) {
    sr064_same('report_export_forbidden', $exception->code(), 'legacy finance export capability is not accepted');
}

$health = (new HealthCheckService())->evaluate($viewer, $facts);
sr064_same('degraded', $health->status, 'dead letters degrade health');
sr064_same('outbox_dead_letters_present', $health->checks['outbox']->reason, 'outbox health reason is visible');
sr064_same('warning', $health->checks['download_settlement']->status, 'download settlement backlog is visible');
sr064_same('ok', $health->checks['audit_freshness']->status, 'audit freshness is visible');

echo "SR-064 reports and health checks passed\n";
