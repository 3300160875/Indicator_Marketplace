<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Outbox;

final readonly class OutboxDeliveryResult
{
    public function __construct(
        public int $processed,
        public int $succeeded,
        public int $retried,
        public int $dead,
    ) {
    }
}

