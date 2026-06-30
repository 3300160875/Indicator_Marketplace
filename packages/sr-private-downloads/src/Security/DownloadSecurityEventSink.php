<?php
declare(strict_types=1);

namespace StockResource\PrivateDownloads\Security;

interface DownloadSecurityEventSink
{
    public function record(SecurityEventRecord $event): void;
}
