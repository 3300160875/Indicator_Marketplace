<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Audit;

use StockResource\AdminOps\Auth\AuthorizationDecision;
use StockResource\AdminOps\Auth\UserContext;

final readonly class AuditDeletePolicy
{
    public function canDelete(UserContext $user): AuthorizationDecision
    {
        return AuthorizationDecision::deny('audit_delete_forbidden');
    }
}
