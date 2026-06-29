<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Infrastructure\Repository;

use RuntimeException;

final class EntitlementException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }

    public static function duplicateSourceOrderItem(int $sourceOrderItemId): self
    {
        return new self(
            'duplicate_source_order_item_id',
            'Entitlement already exists for source order item id '.$sourceOrderItemId.'.',
        );
    }

    public static function invalidEntitlementId(int $entitlementId): self
    {
        if ($entitlementId === 0) {
            return self::idMustBeNew();
        }

        return new self(
            'invalid_entitlement_id',
            'entitlement id must be a positive integer, got '.$entitlementId.'.',
        );
    }

    public static function entitlementNotFound(int $entitlementId): self
    {
        return self::notFound($entitlementId);
    }

    public static function idMustBeNew(): self
    {
        return new self('invalid_entitlement_id', 'create() only accepts unsaved entitlements.');
    }

    public static function notFound(int $entitlementId): self
    {
        return new self('entitlement_not_found', 'Entitlement not found: '.$entitlementId.'.');
    }

    public static function immutableSnapshotConflict(): self
    {
        return new self('snapshot_immutable_conflict', 'Cannot change immutable rule snapshot of existing entitlement.');
    }

    public static function snapshotImmutableConflict(): self
    {
        return self::immutableSnapshotConflict();
    }

    public static function invalidTimeField(string $field): self
    {
        return new self('invalid_time', 'Invalid '.$field.' value.');
    }

    public static function invalidUserId(int $userId): self
    {
        return new self('invalid_user_id', 'user_id must be greater than zero, got '.$userId.'.');
    }
}
