<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Admin;

use StockResource\AdminOps\Auth\UserContext;

final readonly class AdminWorkbenchService
{
    public function __construct(
        private WorkbenchRolePolicy $rolePolicy,
        private WorkbenchActionPolicy $actionPolicy,
    ) {
    }

    /**
     * @param list<WorkbenchTask> $tasks
     */
    public function page(UserContext $user, array $tasks, WorkbenchQuery $query): WorkbenchPage
    {
        $grouped = [];
        foreach ($this->sortTasks($tasks) as $task) {
            if ($query->queue !== null && $task->queue !== $query->queue) {
                continue;
            }

            $projection = $this->rolePolicy->projectTask($user, $task);
            if ($projection === null) {
                continue;
            }

            $grouped[$task->queue][] = $projection;
        }

        $queues = [];
        foreach ($grouped as $queue => $items) {
            $offset = ($query->page - 1) * $query->limit;
            $pageItems = array_slice($items, $offset, $query->limit);
            $queues[$queue] = new WorkbenchQueue($queue, count($items), $pageItems);
        }

        return new WorkbenchPage($queues, $query->page, $query->limit);
    }

    /**
     * @param list<WorkbenchTask> $tasks
     */
    public function authorizeAction(UserContext $user, WorkbenchActionRequest $request, array $tasks): WorkbenchActionDecision
    {
        return $this->actionPolicy->authorize($user, $request, $tasks, $this->rolePolicy);
    }

    /**
     * @param list<WorkbenchTask> $tasks
     * @return list<WorkbenchTask>
     */
    private function sortTasks(array $tasks): array
    {
        $priorityRank = ['urgent' => 0, 'high' => 1, 'normal' => 2, 'low' => 3];
        usort($tasks, static function (WorkbenchTask $left, WorkbenchTask $right) use ($priorityRank): int {
            return ($priorityRank[$left->priority] ?? 99) <=> ($priorityRank[$right->priority] ?? 99)
                ?: strcmp($left->createdAt, $right->createdAt)
                ?: strcmp($left->id, $right->id);
        });

        return $tasks;
    }
}
