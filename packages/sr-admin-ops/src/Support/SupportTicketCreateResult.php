<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Support;

final readonly class SupportTicketCreateResult
{
    /** @param list<SupportAuditEvent> $auditEvents */
    public function __construct(
        public SupportTicket $ticket,
        public array $auditEvents,
    ) {
    }
}
