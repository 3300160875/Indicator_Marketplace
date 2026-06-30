<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Admin;

final readonly class WorkbenchTaskProjection
{
    /**
     * @param array<string, mixed> $fields
     * @param list<string> $allowedActions
     */
    public function __construct(
        public string $id,
        public string $queue,
        public string $label,
        public string $priority,
        public string $status,
        public string $createdAt,
        public array $fields,
        public array $allowedActions,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'queue' => $this->queue,
            'label' => $this->label,
            'priority' => $this->priority,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'fields' => $this->fields,
            'allowed_actions' => $this->allowedActions,
        ];
    }
}
