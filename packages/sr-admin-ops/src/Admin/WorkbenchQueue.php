<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Admin;

final readonly class WorkbenchQueue
{
    /**
     * @param list<WorkbenchTaskProjection> $tasks
     */
    public function __construct(
        public string $queue,
        public int $total,
        public array $tasks,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'queue' => $this->queue,
            'total' => $this->total,
            'tasks' => array_map(static fn (WorkbenchTaskProjection $task): array => $task->toArray(), $this->tasks),
        ];
    }
}
