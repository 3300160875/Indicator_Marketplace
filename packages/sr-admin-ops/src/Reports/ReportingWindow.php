<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Reports;

use DateTimeImmutable;
use DateTimeZone;

final readonly class ReportingWindow
{
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public string $timezone,
        public int $freshnessDelayMinutes,
    ) {
        if ($end < $start) {
            throw new ReportException('invalid_report_window', 'Report end must be after start.');
        }
        if ($freshnessDelayMinutes < 0) {
            throw new ReportException('invalid_freshness_delay', 'Freshness delay must not be negative.');
        }
    }

    public static function fromLocalDates(string $startDate, string $endDate, string $timezone, int $freshnessDelayMinutes): self
    {
        $zone = new DateTimeZone($timezone);

        return new self(
            new DateTimeImmutable($startDate.' 00:00:00', $zone),
            new DateTimeImmutable($endDate.' 23:59:59', $zone),
            $timezone,
            $freshnessDelayMinutes,
        );
    }

    public function daysInclusive(): int
    {
        return ((int) $this->start->diff($this->end)->format('%a')) + 1;
    }
}
