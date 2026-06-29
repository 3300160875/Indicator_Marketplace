<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Outbox;

interface OutboxEventSender
{
    public function send(OutboxEvent $event): void;
}

