<?php
declare(strict_types=1);

namespace StockResource\Core\Support\Audit;

interface AuditService
{
    public function record(AuditEvent $event): void;
}
