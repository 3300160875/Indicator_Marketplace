<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Support;

use StockResource\AdminOps\Auth\UserContext;

final readonly class SupportTicketService
{
    public function __construct(
        private SupportTicketAccessPolicy $accessPolicy,
        private AttachmentPolicy $attachmentPolicy,
        private SupportTicketStateMachine $stateMachine,
        private SupportRelationOwnershipPolicy $ownershipPolicy = new SupportRelationOwnershipPolicy(),
    ) {
    }

    public function createTicket(
        UserContext $user,
        string $ticketNo,
        string $type,
        string $priority,
        string $subject,
        ?int $orderId,
        ?int $resourceId,
        ?int $downloadEventId,
        string $requestId,
        string $nowUtc,
        array $relationOwnerUserIds = [],
    ): SupportTicketCreateResult {
        if ($orderId === null && $resourceId === null && $downloadEventId === null) {
            throw new SupportException('ticket_relation_required', 'Ticket must relate to an order, resource or download event.');
        }

        $ticket = new SupportTicket(
            id: 0,
            ticketNo: $ticketNo,
            userId: $user->userId,
            type: $type,
            status: 'open',
            priority: $priority,
            subject: $subject,
            orderId: $orderId,
            resourceId: $resourceId,
            downloadEventId: $downloadEventId,
            assigneeId: null,
            firstResponseAt: null,
            resolvedAt: null,
            createdAt: $nowUtc,
            updatedAt: $nowUtc,
        );
        $this->ownershipPolicy->assertOwned($user->userId, $ticket, $relationOwnerUserIds);

        $eventName = 'support.ticket_created';
        if (! SupportAuditActionCatalog::knows($eventName)) {
            throw new SupportException('support_audit_action_unknown', 'Support audit action is not registered.');
        }

        return new SupportTicketCreateResult($ticket, [
            new SupportAuditEvent(
                eventName: $eventName,
                ticketId: $ticket->id,
                requestId: $requestId,
                occurredAt: $nowUtc,
                actorId: $user->userId,
                payload: [
                    'ticket_no' => $ticketNo,
                    'type' => $type,
                    'order_id' => $orderId,
                    'resource_id' => $resourceId,
                    'download_event_id' => $downloadEventId,
                ],
            ),
        ]);
    }
}
