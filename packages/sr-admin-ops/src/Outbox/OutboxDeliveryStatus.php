<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Outbox;

enum OutboxDeliveryStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Dead = 'dead';
}
