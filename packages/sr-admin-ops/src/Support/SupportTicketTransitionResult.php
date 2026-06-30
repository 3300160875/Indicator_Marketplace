<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Support;

final readonly class SupportTicketTransitionResult
{
    /** @param list<SupportAuditEvent> $auditEvents */
    public function __construct(
        public SupportTicket $ticket,
        public array $auditEvents,
    ) {
    }

    public function __get(string $name): mixed
    {
        return $this->ticket->{$name};
    }
}
