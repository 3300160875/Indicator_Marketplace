<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Reports;

use DateTimeImmutable;
use StockResource\AdminOps\Auth\UserContext;

final readonly class BusinessReportService
{
    private const MAX_FOREGROUND_DAYS = 31;

    public function __construct(
        private int $maxForegroundRows = 5000,
        private ReportPermissionPolicy $permissionPolicy = new ReportPermissionPolicy(),
    ) {
    }

    public function build(UserContext $user, ReportingWindow $window, ReportFacts $facts): BusinessReport
    {
        if (! $this->permissionPolicy->canView($user)) {
            throw new ReportException('report_view_forbidden', 'User cannot view reports.');
        }
        if ($window->daysInclusive() > self::MAX_FOREGROUND_DAYS) {
            throw new ReportException('report_window_too_large', 'Foreground reports are limited to 31 days.');
        }
        if ($facts->estimatedRows() > $this->maxForegroundRows) {
            throw new ReportException('report_query_too_large', 'Foreground report fact set is too large.');
        }

        $orders = array_values(array_filter(
            $facts->orders,
            fn (array $order): bool => $this->inWindow($order['completed_at'] ?? null, $window),
        ));
        $downloads = array_values(array_filter(
            $facts->downloads,
            fn (array $download): bool => $this->inWindow($download['occurred_at'] ?? null, $window),
        ));
        $reviews = array_values(array_filter(
            $facts->reviews,
            fn (array $review): bool => $this->inWindow($review['submitted_at'] ?? null, $window),
        ));

        $completedOrders = count($orders);
        $ordersWithoutEntitlement = count(array_filter(
            $orders,
            static fn (array $order): bool => ($order['entitlement_created'] ?? false) !== true,
        ));
        $downloadTotal = count($downloads);
        $downloadFailures = count(array_filter(
            $downloads,
            static fn (array $download): bool => ($download['status'] ?? '') === 'failed',
        ));
        $decidedReviewMinutes = array_values(array_filter(array_map(
            static function (array $review): ?float {
                if (($review['decided_at'] ?? null) === null) {
                    return null;
                }

                $submitted = new DateTimeImmutable((string) $review['submitted_at']);
                $decided = new DateTimeImmutable((string) $review['decided_at']);

                return max(0.0, ($decided->getTimestamp() - $submitted->getTimestamp()) / 60);
            },
            $reviews,
        ), static fn (?float $minutes): bool => $minutes !== null));

        return new BusinessReport($window, [
            'completed_orders' => new ReportMetric('completed_orders', $completedOrders, 'count'),
            'orders_without_entitlement' => new ReportMetric('orders_without_entitlement', $ordersWithoutEntitlement, 'count'),
            'download_failure_rate' => new ReportMetric(
                'download_failure_rate',
                $downloadTotal === 0 ? 0.0 : round(($downloadFailures / $downloadTotal) * 100, 2),
                'percent',
            ),
            'average_review_minutes' => new ReportMetric(
                'average_review_minutes',
                $decidedReviewMinutes === [] ? 0.0 : round(array_sum($decidedReviewMinutes) / count($decidedReviewMinutes), 2),
                'minutes',
            ),
        ], [
            'orders_without_entitlement' => new MetricDefinition('orders_without_entitlement', 'Completed orders where no entitlement snapshot was created.'),
            'download_failure_rate' => new MetricDefinition('download_failure_rate', 'Failed download events divided by all download events in the reporting window.'),
            'average_review_minutes' => new MetricDefinition('average_review_minutes', 'Average elapsed minutes from payment proof submission to review decision.'),
            'reporting_timezone' => new MetricDefinition('reporting_timezone', $window->timezone),
            'freshness' => new MetricDefinition('freshness', $window->freshnessDelayMinutes.' minute freshness delay; reports are not real-time.'),
        ], new ReportQueryPlan(
            estimatedRowsScanned: $facts->estimatedRows(),
            foregroundSafe: true,
            maxForegroundRows: $this->maxForegroundRows,
            maxForegroundDays: self::MAX_FOREGROUND_DAYS,
        ));
    }

    private function inWindow(mixed $timestamp, ReportingWindow $window): bool
    {
        if ($timestamp === null || $timestamp === '') {
            return false;
        }

        $date = new DateTimeImmutable((string) $timestamp);

        return $date >= $window->start && $date <= $window->end;
    }
}
