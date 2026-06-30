<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Support;

final readonly class SupportAuditActionCatalog
{
    /**
     * @return list<string>
     */
    public static function actions(): array
    {
        return [
            'support.ticket_created',
            'support.ticket_status_changed',
            'support.message_created',
        ];
    }

    public static function knows(string $eventName): bool
    {
        return in_array($eventName, self::actions(), true);
    }
}
