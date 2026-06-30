<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Support;

final readonly class SupportTicketStateMachine
{
    private const DEFAULT_TRANSITIONS = [
        'open' => ['in_progress', 'waiting_customer', 'resolved', 'closed'],
        'in_progress' => ['waiting_customer', 'resolved', 'closed'],
        'waiting_customer' => ['in_progress', 'resolved', 'closed'],
        'resolved' => ['closed', 'in_progress'],
        'closed' => [],
    ];

    /** @param array<string, list<string>> $transitions */
    public function __construct(private array $transitions = self::DEFAULT_TRANSITIONS)
    {
    }

    public static function defaults(): self
    {
        return new self(self::DEFAULT_TRANSITIONS);
    }

    public function transition(
        SupportTicket $ticket,
        string $targetStatus,
        int $actorId,
        string $reasonCode,
        string $requestId,
        string $nowUtc,
    ): SupportTicketTransitionResult {
        if (! in_array($targetStatus, $this->transitions[$ticket->status] ?? [], true)) {
            throw new SupportException('invalid_ticket_transition', 'Ticket status transition is not allowed.');
        }
        if (trim($reasonCode) === '') {
            throw new SupportException('reason_required', 'Ticket status transitions require a reason code.');
        }

        $next = $ticket->withStatus($targetStatus, $nowUtc);

        $eventName = 'support.ticket_status_changed';
        if (! SupportAuditActionCatalog::knows($eventName)) {
            throw new SupportException('support_audit_action_unknown', 'Support audit action is not registered.');
        }

        return new SupportTicketTransitionResult($next, [
            new SupportAuditEvent(
                eventName: $eventName,
                ticketId: $ticket->id,
                requestId: $requestId,
                occurredAt: $nowUtc,
                actorId: $actorId,
                payload: [
                    'from_status' => $ticket->status,
                    'to_status' => $targetStatus,
                    'reason_code' => $reasonCode,
                ],
            ),
        ]);
    }
}
