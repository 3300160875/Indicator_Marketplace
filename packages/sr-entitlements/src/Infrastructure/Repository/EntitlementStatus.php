<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Infrastructure\Repository;

enum EntitlementStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Suspended = 'suspended';
}

