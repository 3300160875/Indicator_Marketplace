<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$adminOps = $root.'/packages/sr-admin-ops';

foreach ([
    '/src/Auth/AuthorizationDecision.php',
    '/src/Auth/UserContext.php',
    '/src/Admin/AdminWorkbenchException.php',
    '/src/Admin/AdminWorkbenchService.php',
    '/src/Admin/WorkbenchActionDecision.php',
    '/src/Admin/WorkbenchActionPolicy.php',
    '/src/Admin/WorkbenchActionRequest.php',
    '/src/Admin/WorkbenchPage.php',
    '/src/Admin/WorkbenchQuery.php',
    '/src/Admin/WorkbenchQueue.php',
    '/src/Admin/WorkbenchRolePolicy.php',
    '/src/Admin/WorkbenchTask.php',
    '/src/Admin/WorkbenchTaskProjection.php',
] as $file) {
    require_once $adminOps.$file;
}

use StockResource\AdminOps\Admin\AdminWorkbenchException;
use StockResource\AdminOps\Admin\AdminWorkbenchService;
use StockResource\AdminOps\Admin\WorkbenchActionPolicy;
use StockResource\AdminOps\Admin\WorkbenchActionRequest;
use StockResource\AdminOps\Admin\WorkbenchQuery;
use StockResource\AdminOps\Admin\WorkbenchRolePolicy;
use StockResource\AdminOps\Admin\WorkbenchTask;
use StockResource\AdminOps\Auth\UserContext;

function sr063_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr063_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr063_task(array $overrides): WorkbenchTask
{
    return WorkbenchTask::fromArray(array_replace([
        'id' => 'payment:9001',
        'queue' => 'payment',
        'label' => 'Payment proof pending',
        'priority' => 'urgent',
        'status' => 'pending',
        'created_at' => '2026-06-30T00:00:00+00:00',
        'subject_user_id' => 77,
        'subject_resource_id' => null,
        'assignee_id' => null,
        'fields' => [
            'order_id' => 9001,
            'amount' => '99.00',
            'payment_proof_storage_key' => 'private/payment/9001/proof.png',
            'customer_email' => 'customer@example.test',
        ],
        'allowed_actions' => ['approve_payment', 'reject_payment'],
        'source' => 'domain:payment_review',
    ], $overrides));
}

$tasks = [
    sr063_task([]),
    sr063_task([
        'id' => 'membership:8001',
        'queue' => 'membership',
        'label' => 'Manual entitlement revoke requested',
        'priority' => 'high',
        'status' => 'pending',
        'fields' => [
            'entitlement_id' => 8001,
            'plan_code' => 'vip-yearly',
            'internal_note' => 'refund detail should not be public',
        ],
        'allowed_actions' => ['revoke_entitlement'],
        'source' => 'domain:revocation',
    ]),
    sr063_task([
        'id' => 'download:6001',
        'queue' => 'download',
        'label' => 'Download settlement failed',
        'priority' => 'normal',
        'status' => 'failed',
        'fields' => [
            'download_event_id' => 6001,
            'failure_code' => 'storage_missing',
            'signed_url' => 'https://signed.example/private.zip',
        ],
        'allowed_actions' => ['retry_download_settlement'],
        'source' => 'domain:download_settlement',
    ]),
    sr063_task([
        'id' => 'rights:7001',
        'queue' => 'rights',
        'label' => 'Rights evidence expiring',
        'priority' => 'high',
        'status' => 'warning',
        'subject_resource_id' => 7001,
        'fields' => [
            'resource_id' => 7001,
            'rights_status' => 'approved',
            'evidence_storage_key' => 'private/rights/7001/license.pdf',
        ],
        'allowed_actions' => ['pause_publication'],
        'source' => 'domain:rights_gate',
    ]),
];

$service = new AdminWorkbenchService(new WorkbenchRolePolicy(), new WorkbenchActionPolicy());
$finance = new UserContext(501, ['sr_finance_reviewer'], ['claim_sr_payment_submission', 'view_sr_payment_proof', 'approve_sr_payment', 'reject_sr_payment']);
$membership = new UserContext(502, ['sr_membership_operator'], ['view_sr_entitlements', 'revoke_sr_entitlement']);
$support = new UserContext(503, ['sr_support_agent'], ['view_limited_order_projection']);
$rights = new UserContext(504, ['sr_rights_reviewer'], ['view_sr_rights_evidence', 'take_down_sr_resources']);
$outsider = new UserContext(505, ['sr_resource_editor'], ['edit_sr_resources']);

$financePage = $service->page($finance, $tasks, new WorkbenchQuery(limit: 10, page: 1));
sr063_same(['payment'], array_keys($financePage->queues), 'finance workbench is task-oriented by visible queue');
sr063_same(1, $financePage->queues['payment']->total, 'finance queue counts payment tasks');
$financePayload = $financePage->toArray();
$encodedFinance = json_encode($financePayload, JSON_THROW_ON_ERROR);
sr063_true(str_contains($encodedFinance, 'Payment proof pending'), 'finance sees payment todo label');
sr063_true(! str_contains($encodedFinance, 'private/payment'), 'finance projection does not expose storage key');
sr063_true(! str_contains($encodedFinance, 'customer@example.test'), 'finance projection hides customer email by default');

$membershipPage = $service->page($membership, $tasks, new WorkbenchQuery(limit: 10, page: 1));
sr063_same(['membership'], array_keys($membershipPage->queues), 'membership operator sees membership queue only');
$membershipPayload = json_encode($membershipPage->toArray(), JSON_THROW_ON_ERROR);
sr063_true(str_contains($membershipPayload, 'vip-yearly'), 'membership projection includes plan code');
sr063_true(! str_contains($membershipPayload, 'refund detail'), 'membership projection hides internal notes');

$supportPage = $service->page($support, $tasks, new WorkbenchQuery(limit: 10, page: 1));
sr063_same(['download'], array_keys($supportPage->queues), 'support sees download queue with limited projection');
$supportPayload = json_encode($supportPage->toArray(), JSON_THROW_ON_ERROR);
sr063_true(str_contains($supportPayload, 'storage_missing'), 'support sees failure code');
sr063_true(! str_contains($supportPayload, 'https://signed.example'), 'support projection hides signed URLs');
sr063_true(! str_contains($supportPayload, 'retry_download_settlement'), 'support view permission does not expose mutating retry action');

$rightsPage = $service->page($rights, $tasks, new WorkbenchQuery(limit: 10, page: 1));
sr063_same(['rights'], array_keys($rightsPage->queues), 'rights reviewer sees rights queue only');
$rightsPayload = json_encode($rightsPage->toArray(), JSON_THROW_ON_ERROR);
sr063_true(str_contains($rightsPayload, 'rights_status'), 'rights projection includes status');
sr063_true(! str_contains($rightsPayload, 'private/rights'), 'rights projection hides evidence storage key');

$outsiderPage = $service->page($outsider, $tasks, new WorkbenchQuery(limit: 10, page: 1));
sr063_same([], $outsiderPage->queues, 'unprivileged editor sees no admin workbench queues');

try {
    new WorkbenchQuery(limit: 101, page: 1);
    throw new RuntimeException('oversized page should fail');
} catch (AdminWorkbenchException $exception) {
    sr063_same('invalid_page_limit', $exception->code(), 'pagination limit has stable error code');
}

$approval = $service->authorizeAction($finance, new WorkbenchActionRequest(
    action: 'approve_payment',
    itemIds: ['payment:9001'],
    reasonCode: 'proof_matched',
    confirmationPhrase: 'CONFIRM',
    requestId: '11111111-1111-4111-8111-111111111111',
), $tasks);
sr063_same(true, $approval->allowed, 'high-risk payment approval is allowed with reason and confirmation');
sr063_same(true, $approval->requiresAudit, 'high-risk action requires audit');
sr063_same('payment.approved', $approval->auditAction, 'payment approval maps to audit action');
sr063_same(1, $approval->auditMetadata['audit_record_count'], 'audit metadata exposes per-item audit record count');
sr063_same('payment:9001', $approval->auditRecords[0]['item_id'], 'audit records are emitted per item');

$missingConfirmation = $service->authorizeAction($finance, new WorkbenchActionRequest(
    action: 'approve_payment',
    itemIds: ['payment:9001'],
    reasonCode: 'proof_matched',
    confirmationPhrase: '',
    requestId: '22222222-2222-4222-8222-222222222222',
), $tasks);
sr063_same(false, $missingConfirmation->allowed, 'high-risk action without confirmation is denied');
sr063_same('confirmation_required', $missingConfirmation->reason, 'missing confirmation has stable reason');

$missingReason = $service->authorizeAction($membership, new WorkbenchActionRequest(
    action: 'revoke_entitlement',
    itemIds: ['membership:8001'],
    reasonCode: '',
    confirmationPhrase: 'CONFIRM',
    requestId: '33333333-3333-4333-8333-333333333333',
), $tasks);
sr063_same(false, $missingReason->allowed, 'high-risk action without reason is denied');
sr063_same('reason_required', $missingReason->reason, 'missing reason has stable reason');

$notAllowed = $service->authorizeAction($support, new WorkbenchActionRequest(
    action: 'approve_payment',
    itemIds: ['payment:9001'],
    reasonCode: 'proof_matched',
    confirmationPhrase: 'CONFIRM',
    requestId: '44444444-4444-4444-8444-444444444444',
), $tasks);
sr063_same(false, $notAllowed->allowed, 'support cannot approve payments');
sr063_same('capability_required', $notAllowed->reason, 'capability denial has stable reason');

$wrongTaskQueue = $service->authorizeAction($finance, new WorkbenchActionRequest(
    action: 'approve_payment',
    itemIds: ['membership:8001'],
    reasonCode: 'proof_matched',
    confirmationPhrase: 'CONFIRM',
    requestId: '45454545-4545-4545-8545-454545454545',
), $tasks);
sr063_same(false, $wrongTaskQueue->allowed, 'payment action cannot target membership task');
sr063_same('action_queue_mismatch', $wrongTaskQueue->reason, 'task queue mismatch has stable reason');

$taskDisallowedAction = $service->authorizeAction($finance, new WorkbenchActionRequest(
    action: 'reject_payment',
    itemIds: ['payment:locked'],
    reasonCode: 'proof_mismatch',
    confirmationPhrase: 'CONFIRM',
    requestId: '46464646-4646-4646-8646-464646464646',
), [
    sr063_task([
        'id' => 'payment:locked',
        'allowed_actions' => ['approve_payment'],
    ]),
]);
sr063_same(false, $taskDisallowedAction->allowed, 'domain task allowedActions are enforced');
sr063_same('action_not_allowed_for_task', $taskDisallowedAction->reason, 'task action denial has stable reason');

$retryDownload = $service->authorizeAction($support, new WorkbenchActionRequest(
    action: 'retry_download_settlement',
    itemIds: ['download:6001'],
    reasonCode: 'manual_retry',
    confirmationPhrase: 'CONFIRM',
    requestId: '47474747-4747-4747-8747-474747474747',
), $tasks);
sr063_same(false, $retryDownload->allowed, 'download retry is not authorized by view-only support capability');
sr063_same('unknown_action', $retryDownload->reason, 'undefined mutating download action is unavailable');

$tooManyItems = $service->authorizeAction($finance, new WorkbenchActionRequest(
    action: 'approve_payment',
    itemIds: array_map(static fn (int $i): string => 'payment:'.$i, range(1, 51)),
    reasonCode: 'batch_review',
    confirmationPhrase: 'CONFIRM',
    requestId: '55555555-5555-4555-8555-555555555555',
), $tasks);
sr063_same(false, $tooManyItems->allowed, 'bulk action max item count is enforced');
sr063_same('bulk_limit_exceeded', $tooManyItems->reason, 'bulk limit denial has stable reason');

echo "SR-063 admin workbench checks passed\n";
