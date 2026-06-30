<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Admin;

final readonly class WorkbenchQuery
{
    public function __construct(
        public int $limit = 25,
        public int $page = 1,
        public ?string $queue = null,
    ) {
        if ($limit < 1 || $limit > 100) {
            throw new AdminWorkbenchException('invalid_page_limit', 'Workbench page limit must be between 1 and 100.');
        }
        if ($page < 1) {
            throw new AdminWorkbenchException('invalid_page', 'Workbench page must be positive.');
        }
        if ($queue !== null && ! in_array($queue, ['payment', 'membership', 'download', 'rights'], true)) {
            throw new AdminWorkbenchException('invalid_queue', 'Workbench queue is unsupported.');
        }
    }
}
