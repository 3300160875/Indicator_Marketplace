<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Admin;

final readonly class WorkbenchActionRequest
{
    /**
     * @param list<string> $itemIds
     */
    public function __construct(
        public string $action,
        public array $itemIds,
        public string $reasonCode,
        public string $confirmationPhrase,
        public string $requestId,
    ) {
        if ($action === '') {
            throw new AdminWorkbenchException('action_required', 'Workbench action is required.');
        }
        if ($itemIds === []) {
            throw new AdminWorkbenchException('items_required', 'Workbench action requires at least one item.');
        }
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $requestId)) {
            throw new AdminWorkbenchException('invalid_request_id', 'Workbench action requires a request ID.');
        }
    }
}
