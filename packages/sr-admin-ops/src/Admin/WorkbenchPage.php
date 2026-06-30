<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Admin;

final readonly class WorkbenchPage
{
    /**
     * @param array<string, WorkbenchQueue> $queues
     */
    public function __construct(
        public array $queues,
        public int $page,
        public int $limit,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'limit' => $this->limit,
            'queues' => array_map(static fn (WorkbenchQueue $queue): array => $queue->toArray(), $this->queues),
        ];
    }
}
