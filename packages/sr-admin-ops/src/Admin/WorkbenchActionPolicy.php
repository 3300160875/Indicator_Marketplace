<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Admin;

use StockResource\AdminOps\Auth\UserContext;

final readonly class WorkbenchActionPolicy
{
    private const ACTIONS = [
        'approve_payment' => ['queue' => 'payment', 'capability' => 'approve_sr_payment', 'audit_action' => 'payment.approved'],
        'reject_payment' => ['queue' => 'payment', 'capability' => 'reject_sr_payment', 'audit_action' => 'payment.rejected'],
        'revoke_entitlement' => ['queue' => 'membership', 'capability' => 'revoke_sr_entitlement', 'audit_action' => 'entitlement.revoked'],
        'pause_publication' => ['queue' => 'rights', 'capability' => 'take_down_sr_resources', 'audit_action' => 'resource.unpublished'],
    ];

    public static function userHasActionCapability(UserContext $user, string $action): bool
    {
        $definition = self::ACTIONS[$action] ?? null;

        return $definition !== null && $user->hasCapability($definition['capability']);
    }

    /**
     * @param list<WorkbenchTask> $tasks
     */
    public function authorize(
        UserContext $user,
        WorkbenchActionRequest $request,
        array $tasks,
        WorkbenchRolePolicy $rolePolicy,
    ): WorkbenchActionDecision {
        if (count($request->itemIds) > 50) {
            return WorkbenchActionDecision::deny('bulk_limit_exceeded');
        }

        $definition = self::ACTIONS[$request->action] ?? null;
        if ($definition === null) {
            return WorkbenchActionDecision::deny('unknown_action');
        }
        if (! $user->hasCapability($definition['capability'])) {
            return WorkbenchActionDecision::deny('capability_required');
        }
        $tasksById = [];
        foreach ($tasks as $task) {
            $tasksById[$task->id] = $task;
        }
        foreach ($request->itemIds as $itemId) {
            $task = $tasksById[$itemId] ?? null;
            if ($task === null) {
                return WorkbenchActionDecision::deny('item_not_found');
            }
            if ($task->queue !== $definition['queue']) {
                return WorkbenchActionDecision::deny('action_queue_mismatch');
            }
            if (! $rolePolicy->canSeeQueue($user, $task->queue)) {
                return WorkbenchActionDecision::deny('item_queue_not_visible');
            }
            if (! in_array($request->action, $task->allowedActions, true)) {
                return WorkbenchActionDecision::deny('action_not_allowed_for_task');
            }
        }
        if (trim($request->reasonCode) === '') {
            return WorkbenchActionDecision::deny('reason_required');
        }
        if ($request->confirmationPhrase !== 'CONFIRM') {
            return WorkbenchActionDecision::deny('confirmation_required');
        }

        return WorkbenchActionDecision::allow(
            $definition['audit_action'],
            array_map(
                fn (string $itemId): array => [
                    'action' => $request->action,
                    'item_id' => $itemId,
                    'reason_code' => $request->reasonCode,
                    'request_id' => $request->requestId,
                ],
                $request->itemIds,
            ),
        );
    }
}
