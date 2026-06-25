<?php
declare(strict_types=1);

namespace StockResource\Core\Version;

enum ResourceVersionStatus: string
{
    case Draft = 'draft';
    case Scanning = 'scanning';
    case Review = 'review';
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
