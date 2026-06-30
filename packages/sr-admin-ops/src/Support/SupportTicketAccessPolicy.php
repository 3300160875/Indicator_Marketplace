<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Support;

use StockResource\AdminOps\Auth\AuthorizationDecision;
use StockResource\AdminOps\Auth\UserContext;

final readonly class SupportTicketAccessPolicy
{
    public function canView(UserContext $user, SupportTicket $ticket): AuthorizationDecision
    {
        if ($user->userId === $ticket->userId) {
            return AuthorizationDecision::allow();
        }

        if ($user->hasCapability('view_assigned_sr_tickets') && $ticket->assigneeId === $user->userId) {
            return AuthorizationDecision::allow();
        }

        return AuthorizationDecision::deny('ticket_not_visible');
    }
}
