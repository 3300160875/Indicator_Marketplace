<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Reports;

use DateTimeImmutable;
use StockResource\AdminOps\Auth\UserContext;

final readonly class HealthCheckService
{
    public function __construct(private ReportPermissionPolicy $permissionPolicy = new ReportPermissionPolicy())
    {
    }

    public function evaluate(UserContext $user, ReportFacts $facts): HealthCheckResult
    {
        if (! $this->permissionPolicy->canView($user)) {
            throw new ReportException('report_view_forbidden', 'User cannot view report health.');
        }

        $deadLetters = (int) ($facts->health['outbox_dead_letters'] ?? 0);
        $downloadBacklog = (int) ($facts->health['download_settlement_backlog'] ?? 0);
        $auditAgeMinutes = $this->auditAgeMinutes($facts->health);
        $checks = [
            'outbox' => (object) [
                'status' => $deadLetters > 0 ? 'degraded' : 'ok',
                'reason' => $deadLetters > 0 ? 'outbox_dead_letters_present' : 'outbox_ok',
                'value' => $deadLetters,
            ],
            'download_settlement' => (object) [
                'status' => $downloadBacklog > 0 ? 'warning' : 'ok',
                'reason' => $downloadBacklog > 0 ? 'download_settlement_backlog_present' : 'download_settlement_ok',
                'value' => $downloadBacklog,
            ],
            'audit_freshness' => (object) [
                'status' => $auditAgeMinutes <= 60 ? 'ok' : 'warning',
                'reason' => $auditAgeMinutes <= 60 ? 'audit_fresh' : 'audit_stale',
                'value' => $auditAgeMinutes,
            ],
        ];

        $overall = 'ok';
        foreach ($checks as $check) {
            if ($check->status === 'degraded') {
                $overall = 'degraded';
                break;
            }
            if ($check->status === 'warning') {
                $overall = 'warning';
            }
        }

        return new HealthCheckResult($overall, $checks);
    }

    /** @param array<string, mixed> $health */
    private function auditAgeMinutes(array $health): int
    {
        if (! isset($health['audit_log_latest_at'], $health['now'])) {
            return 999999;
        }

        $latest = new DateTimeImmutable((string) $health['audit_log_latest_at']);
        $now = new DateTimeImmutable((string) $health['now']);

        return max(0, (int) floor(($now->getTimestamp() - $latest->getTimestamp()) / 60));
    }
}
