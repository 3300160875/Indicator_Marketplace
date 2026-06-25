<?php
declare(strict_types=1);

namespace StockResource\Core\Version;

enum ResourceVersionScanStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Scanning = 'scanning';
    case Clean = 'clean';
    case Infected = 'infected';
    case Failed = 'failed';
}
