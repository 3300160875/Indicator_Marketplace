<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Admin;

use StockResource\AdminOps\Auth\UserContext;

final readonly class WorkbenchRolePolicy
{
    /** @return list<string> */
    public function visibleQueues(UserContext $user): array
    {
        $queues = [];
        if ($user->hasCapability('claim_sr_payment_submission') || $user->hasCapability('approve_sr_payment') || $user->hasCapability('reject_sr_payment')) {
            $queues[] = 'payment';
        }
        if ($user->hasCapability('view_sr_entitlements') || $user->hasCapability('revoke_sr_entitlement') || $user->hasCapability('manually_grant_sr_entitlement')) {
            $queues[] = 'membership';
        }
        if ($user->hasCapability('view_limited_order_projection')) {
            $queues[] = 'download';
        }
        if ($user->hasCapability('view_sr_rights_evidence') || $user->hasCapability('take_down_sr_resources') || $user->hasCapability('sr_review_rights_evidence')) {
            $queues[] = 'rights';
        }

        return array_values(array_unique($queues));
    }

    public function canSeeQueue(UserContext $user, string $queue): bool
    {
        return in_array($queue, $this->visibleQueues($user), true);
    }

    /**
     * @return list<string>
     */
    public function allowedFieldKeys(UserContext $user, string $queue): array
    {
        return match ($queue) {
            'payment' => ['order_id', 'amount', 'channel', 'reported_paid_at'],
            'membership' => ['entitlement_id', 'plan_code', 'status', 'expires_at'],
            'download' => ['download_event_id', 'failure_code', 'resource_id', 'order_id'],
            'rights' => ['resource_id', 'rights_status', 'expires_at', 'warning_code'],
            default => [],
        };
    }

    public function projectTask(UserContext $user, WorkbenchTask $task): ?WorkbenchTaskProjection
    {
        if (! $this->canSeeQueue($user, $task->queue)) {
            return null;
        }

        $allowedFields = array_flip($this->allowedFieldKeys($user, $task->queue));
        $fields = array_intersect_key($task->fields, $allowedFields);

        return new WorkbenchTaskProjection(
            id: $task->id,
            queue: $task->queue,
            label: $task->label,
            priority: $task->priority,
            status: $task->status,
            createdAt: $task->createdAt,
            fields: $fields,
            allowedActions: array_values(array_filter(
                $task->allowedActions,
                fn (string $action): bool => WorkbenchActionPolicy::userHasActionCapability($user, $action),
            )),
        );
    }
}
