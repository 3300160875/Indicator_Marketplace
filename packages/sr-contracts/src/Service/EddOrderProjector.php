<?php
declare(strict_types=1);

namespace StockResource\Contracts\Service;

use StockResource\Contracts\Dto\OrderCompletedEvent;
use StockResource\Contracts\Dto\OrderRefundedEvent;

interface EddOrderProjector
{
    public function projectCompletedOrder(OrderCompletedEvent $event): void;

    public function projectRefundedOrder(OrderRefundedEvent $event): void;
}
