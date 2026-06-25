<?php
declare(strict_types=1);

namespace StockResource\Contracts\Enum;

enum AccessSource: string
{
    case Free = 'FREE';
    case Purchase = 'PURCHASE';
    case Manual = 'MANUAL';
    case Vip = 'VIP';
    case None = 'NONE';
}
