<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Support;

final readonly class SupportRelationOwnershipPolicy
{
    /**
     * @param array<string, int|null> $relationOwnerUserIds
     */
    public function assertOwned(int $userId, SupportTicket $ticket, array $relationOwnerUserIds): void
    {
        foreach ([
            'order_id' => $ticket->orderId,
            'resource_id' => $ticket->resourceId,
            'download_event_id' => $ticket->downloadEventId,
        ] as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (! array_key_exists($key, $relationOwnerUserIds)) {
                throw new SupportException('relation_owner_required', $key.' owner must be provided.');
            }

            if ($relationOwnerUserIds[$key] !== $userId) {
                throw new SupportException('relation_not_owned', $key.' does not belong to the requesting user.');
            }
        }
    }
}
