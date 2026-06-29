<?php

declare(strict_types=1);

namespace StockResource\Core\Commerce;

enum AccessMode: string
{
    case Free = 'free';
    case Purchase = 'purchase';
    case Vip = 'vip';
    case PurchaseOrVip = 'purchase_or_vip';
    case Unavailable = 'unavailable';

    public function requiresPrice(): bool
    {
        return in_array($this, [self::Purchase, self::PurchaseOrVip], true);
    }

    public function producesPayableOrder(): bool
    {
        return $this->requiresPrice();
    }
}
