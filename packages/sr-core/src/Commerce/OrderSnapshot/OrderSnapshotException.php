<?php

declare(strict_types=1);

namespace StockResource\Core\Commerce\OrderSnapshot;

use RuntimeException;

final class OrderSnapshotException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }

    public static function orderNotOwned(int $orderId, int $userId): self
    {
        return new self('order_not_owned', 'Order '.$orderId.' is not owned by user '.$userId.'.');
    }

    public static function missingUserMapping(int $orderId): self
    {
        return new self('missing_user_mapping', 'Order '.$orderId.' does not have a stable WordPress user mapping.');
    }

    public static function refundOrderNotAccessible(int $orderId): self
    {
        return new self('refund_order_not_accessible', 'Refund order '.$orderId.' cannot be used as a business snapshot source.');
    }

    public static function invalidSnapshot(string $reason): self
    {
        return new self('invalid_snapshot', $reason);
    }
}
