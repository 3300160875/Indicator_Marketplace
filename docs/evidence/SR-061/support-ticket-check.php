<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$adminOps = $root.'/packages/sr-admin-ops';

foreach ([
    '/src/Auth/AuthorizationDecision.php',
    '/src/Auth/AuthorizationService.php',
    '/src/Auth/CapabilityDefinition.php',
    '/src/Auth/OwnedResourceSubject.php',
    '/src/Auth/RoleCapabilityMatrix.php',
    '/src/Auth/RoleDefinition.php',
    '/src/Auth/UserContext.php',
    '/src/Support/AttachmentPolicy.php',
    '/src/Support/SupportAuditActionCatalog.php',
    '/src/Support/SupportAuditEvent.php',
    '/src/Support/SupportException.php',
    '/src/Support/SupportMessage.php',
    '/src/Support/SupportRelationOwnershipPolicy.php',
    '/src/Support/SupportSlaPolicy.php',
    '/src/Support/SupportTicket.php',
    '/src/Support/SupportTicketAccessPolicy.php',
    '/src/Support/SupportTicketCreateResult.php',
    '/src/Support/SupportTicketService.php',
    '/src/Support/SupportTicketStateMachine.php',
    '/src/Support/SupportTicketTransitionResult.php',
] as $file) {
    require_once $adminOps.$file;
}

use StockResource\AdminOps\Auth\RoleCapabilityMatrix;
use StockResource\AdminOps\Auth\UserContext;
use StockResource\AdminOps\Support\AttachmentPolicy;
use StockResource\AdminOps\Support\SupportAuditActionCatalog;
use StockResource\AdminOps\Support\SupportException;
use StockResource\AdminOps\Support\SupportMessage;
use StockResource\AdminOps\Support\SupportSlaPolicy;
use StockResource\AdminOps\Support\SupportTicket;
use StockResource\AdminOps\Support\SupportTicketAccessPolicy;
use StockResource\AdminOps\Support\SupportTicketService;
use StockResource\AdminOps\Support\SupportTicketStateMachine;

function sr061_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr061_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$ticket = SupportTicket::fromArray([
    'id' => 301,
    'ticket_no' => 'T-20260630-0001',
    'user_id' => 77,
    'type' => 'download',
    'status' => 'open',
    'priority' => 'high',
    'subject' => 'Download failed',
    'order_id' => 9001,
    'resource_id' => 7001,
    'download_event_id' => 6001,
    'assignee_id' => 501,
    'created_at' => '2026-06-30T00:00:00+00:00',
    'updated_at' => '2026-06-30T00:00:00+00:00',
]);
sr061_same(9001, $ticket->orderId, 'ticket links order');
sr061_same(7001, $ticket->resourceId, 'ticket links resource');
sr061_same(6001, $ticket->downloadEventId, 'ticket links download event');
sr061_true(SupportAuditActionCatalog::knows('support.ticket_created'), 'ticket creation audit action is registered');
sr061_true(SupportAuditActionCatalog::knows('support.ticket_status_changed'), 'ticket status audit action is registered');

$paymentTicket = SupportTicket::fromArray([
    'id' => 303,
    'ticket_no' => 'T-20260630-0004',
    'user_id' => 77,
    'type' => 'payment',
    'status' => 'open',
    'priority' => 'normal',
    'subject' => 'Payment receipt question',
    'order_id' => 9001,
    'created_at' => '2026-06-30T00:00:00+00:00',
    'updated_at' => '2026-06-30T00:00:00+00:00',
]);
sr061_same('payment', $paymentTicket->type, 'payment ticket type is supported');
try {
    SupportTicket::fromArray([
        'id' => 304,
        'ticket_no' => 'T-20260630-0005',
        'user_id' => 77,
        'type' => 'other',
        'status' => 'open',
        'priority' => 'normal',
        'subject' => 'No relation',
        'created_at' => '2026-06-30T00:00:00+00:00',
        'updated_at' => '2026-06-30T00:00:00+00:00',
    ]);
    throw new RuntimeException('ticket without relation should fail');
} catch (SupportException $exception) {
    sr061_same('ticket_relation_required', $exception->code(), 'hydrated tickets require a relation');
}

$matrix = RoleCapabilityMatrix::defaults();
$owner = new UserContext(77, ['customer'], ['read']);
$otherCustomer = new UserContext(78, ['customer'], ['read']);
$support = UserContext::fromRoles(501, ['sr_customer_support'], $matrix);
$contractSupport = new UserContext(501, ['sr_support_agent'], ['view_assigned_sr_tickets', 'reply_sr_tickets']);
$entitlementViewer = new UserContext(501, ['sr_entitlement_viewer'], ['sr_view_customer_entitlements']);
$access = new SupportTicketAccessPolicy();
sr061_same(true, $access->canView($owner, $ticket)->allowed, 'ticket owner can view ticket');
sr061_same(false, $access->canView($otherCustomer, $ticket)->allowed, 'other customer cannot view ticket');
sr061_same(false, $access->canView($support, $ticket)->allowed, 'legacy support role without ticket capability cannot view tickets');
sr061_same(true, $access->canView($contractSupport, $ticket)->allowed, 'contract support capability can view assigned tickets');
sr061_same(false, $access->canView($entitlementViewer, $ticket)->allowed, 'entitlement viewer capability cannot view support tickets');
sr061_same(false, $access->canView($support, SupportTicket::fromArray([
    'id' => 302,
    'ticket_no' => 'T-20260630-0003',
    'user_id' => 77,
    'type' => 'download',
    'status' => 'open',
    'priority' => 'normal',
    'subject' => 'Unassigned',
    'order_id' => 9001,
    'resource_id' => 7001,
    'download_event_id' => 6001,
    'assignee_id' => null,
    'created_at' => '2026-06-30T00:00:00+00:00',
    'updated_at' => '2026-06-30T00:00:00+00:00',
]))->allowed, 'support role cannot view unassigned tickets through assigned-ticket permission');

$attachmentPolicy = new AttachmentPolicy();
sr061_true($attachmentPolicy->isPrivateStorageKey('private/support/301/error.png'), 'private support attachment accepted');
sr061_same(false, $attachmentPolicy->isPrivateStorageKey('https://cdn.example/error.png'), 'public attachment URL rejected');
try {
    SupportMessage::customer(
        id: 0,
        ticketId: 301,
        actorId: 77,
        body: 'Customer message',
        attachmentStorageKey: '../error.png',
        createdAt: '2026-06-30T00:01:00+00:00',
    );
    throw new RuntimeException('unsafe attachment should fail');
} catch (SupportException $exception) {
    sr061_same('attachment_not_private', $exception->code(), 'unsafe attachment has stable error code');
}

$customerMessage = SupportMessage::customer(
    id: 0,
    ticketId: 301,
    actorId: 77,
    body: 'I cannot download the file.',
    attachmentStorageKey: 'private/support/301/error.png',
    createdAt: '2026-06-30T00:01:00+00:00',
);
$internalNote = SupportMessage::internal(
    id: 0,
    ticketId: 301,
    actorId: 501,
    body: 'Refund-sensitive internal note.',
    createdAt: '2026-06-30T00:02:00+00:00',
);
sr061_same(true, $customerMessage->visibleToCustomer(), 'customer message is visible to customer');
sr061_same(false, $internalNote->visibleToCustomer(), 'internal note is hidden from customer');
sr061_same([$customerMessage], SupportMessage::customerVisible([$customerMessage, $internalNote]), 'customer projection excludes internal notes');
$customerPayload = $customerMessage->customerPayload();
sr061_same(true, $customerPayload['has_attachment'], 'customer payload exposes attachment presence');
sr061_same(false, array_key_exists('attachmentStorageKey', $customerPayload), 'customer payload hides storage key');
$customerPayloads = SupportMessage::customerVisiblePayloads([$customerMessage, $internalNote]);
sr061_same(1, count($customerPayloads), 'customer payload list excludes internal notes');
sr061_same(false, array_key_exists('attachmentStorageKey', $customerPayloads[0]), 'customer payload list hides storage keys');
try {
    $internalNote->customerPayload();
    throw new RuntimeException('internal note projection should fail');
} catch (SupportException $exception) {
    sr061_same('message_not_customer_visible', $exception->code(), 'internal notes cannot be projected to customers');
}

$sla = SupportSlaPolicy::fromConfig(['high_response_minutes' => 60, 'normal_response_minutes' => 240]);
sr061_same('2026-06-30T01:00:00+00:00', $sla->firstResponseDueAt($ticket), 'high priority SLA due time is configurable');
sr061_same(true, $sla->isBreached($ticket, '2026-06-30T01:01:00+00:00'), 'SLA breach is detected');

$stateMachine = new SupportTicketStateMachine();
$inProgress = $stateMachine->transition($ticket, 'in_progress', 501, 'investigating', '55555555-5555-4555-8555-555555555555', '2026-06-30T00:05:00+00:00');
sr061_same('in_progress', $inProgress->status, 'state transition updates ticket status');
sr061_same('support.ticket_status_changed', $inProgress->auditEvents[0]->eventName, 'state transition emits audit');
sr061_same('investigating', $inProgress->auditEvents[0]->payload['reason_code'], 'state audit records reason code');
$limitedStateMachine = new SupportTicketStateMachine(['open' => ['closed'], 'closed' => []]);
try {
    $limitedStateMachine->transition($ticket, 'in_progress', 501, 'blocked', '77777777-7777-4777-8777-777777777777', '2026-06-30T00:06:00+00:00');
    throw new RuntimeException('disallowed configured transition should fail');
} catch (SupportException $exception) {
    sr061_same('invalid_ticket_transition', $exception->code(), 'custom state machine rejects unconfigured transition');
}
$closed = $limitedStateMachine->transition($ticket, 'closed', 501, 'resolved_elsewhere', '88888888-8888-4888-8888-888888888888', '2026-06-30T00:07:00+00:00');
sr061_same('closed', $closed->status, 'custom state machine allows configured transition');

$service = new SupportTicketService($access, $attachmentPolicy, $stateMachine);
$created = $service->createTicket(
    user: $owner,
    ticketNo: 'T-20260630-0002',
    type: 'download',
    priority: 'normal',
    subject: 'Need help',
    orderId: 9001,
    resourceId: 7001,
    downloadEventId: 6001,
    requestId: '66666666-6666-4666-8666-666666666666',
    nowUtc: '2026-06-30T00:10:00+00:00',
    relationOwnerUserIds: ['order_id' => 77, 'resource_id' => 77, 'download_event_id' => 77],
);
sr061_same(77, $created->ticket->userId, 'created ticket belongs to requesting user');
sr061_same('support.ticket_created', $created->auditEvents[0]->eventName, 'ticket creation emits audit');
sr061_same(6001, $created->ticket->downloadEventId, 'created ticket keeps download event relation');
try {
    $service->createTicket(
        user: $owner,
        ticketNo: 'T-20260630-0006',
        type: 'download',
        priority: 'normal',
        subject: 'Wrong owner',
        orderId: 9002,
        resourceId: null,
        downloadEventId: null,
        requestId: '99999999-9999-4999-8999-999999999999',
        nowUtc: '2026-06-30T00:11:00+00:00',
        relationOwnerUserIds: ['order_id' => 78],
    );
    throw new RuntimeException('foreign relation should fail');
} catch (SupportException $exception) {
    sr061_same('relation_not_owned', $exception->code(), 'foreign relation cannot be used to create ticket');
}
try {
    $service->createTicket(
        user: $owner,
        ticketNo: 'T-20260630-0007',
        type: 'download',
        priority: 'normal',
        subject: 'Missing owner',
        orderId: null,
        resourceId: 7002,
        downloadEventId: null,
        requestId: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        nowUtc: '2026-06-30T00:12:00+00:00',
        relationOwnerUserIds: [],
    );
    throw new RuntimeException('missing owner proof should fail');
} catch (SupportException $exception) {
    sr061_same('relation_owner_required', $exception->code(), 'relation owner proof is required');
}

echo "SR-061 support ticket checks passed\n";
