<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Audit;

use StockResource\AdminOps\Auth\UserContext;

final readonly class AuditQueryService
{
    public function __construct(private AuditLogRepository $repository)
    {
    }

    /**
     * @return list<AuditLogRecord>
     */
    public function query(UserContext $user, AuditLogQuery $query): array
    {
        $records = $this->repository->query($query);
        $visible = array_values(array_filter(
            $records,
            fn (AuditLogRecord $record): bool => $this->canViewRecord($user, $record),
        ));

        return array_slice($visible, 0, $query->limit);
    }

    private function canViewRecord(UserContext $user, AuditLogRecord $record): bool
    {
        if ($user->hasCapability('view_sr_audit_logs')) {
            return true;
        }

        if ($user->hasRole('sr_operations_manager')) {
            return in_array($record->subjectType, ['resource', 'feature_flag'], true);
        }

        if ($user->hasRole('sr_customer_support')) {
            return in_array($record->subjectType, ['download_event', 'ticket'], true);
        }

        if ($user->hasRole('sr_finance_operator')) {
            return in_array($record->subjectType, ['payment_submission', 'order'], true);
        }

        return false;
    }
}
