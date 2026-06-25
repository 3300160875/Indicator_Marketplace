<?php
declare(strict_types=1);

namespace StockResource\Core\Version;

final readonly class ResourceVersionWorkflowStage
{
    public function __construct(
        public string $key,
        public bool $retryable,
        public bool $usesTransactionLock,
    ) {
    }
}
