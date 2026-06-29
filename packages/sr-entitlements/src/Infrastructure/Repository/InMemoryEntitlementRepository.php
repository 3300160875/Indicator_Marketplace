<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Infrastructure\Repository;

final class InMemoryEntitlementRepository implements EntitlementRepository
{
    /**
     * @var array<int, Entitlement>
     */
    private array $entitlements = [];

    /**
     * @var array<int, int>
     */
    private array $sourceOrderItemIndex = [];

    public function create(Entitlement $entitlement): Entitlement
    {
        if ($entitlement->id !== 0) {
            throw EntitlementException::invalidEntitlementId($entitlement->id);
        }

        $id = $this->nextId();
        $persisted = $entitlement->withId($id);

        if ($persisted->sourceOrderItemId !== null && isset($this->sourceOrderItemIndex[$persisted->sourceOrderItemId])) {
            throw EntitlementException::duplicateSourceOrderItem($persisted->sourceOrderItemId);
        }

        if (isset($this->entitlements[$id])) {
            throw EntitlementException::invalidEntitlementId($id);
        }

        $this->entitlements[$id] = $persisted;
        if ($persisted->sourceOrderItemId !== null) {
            $this->sourceOrderItemIndex[$persisted->sourceOrderItemId] = $id;
        }

        return $persisted;
    }

    public function save(Entitlement $entitlement): Entitlement
    {
        if ($entitlement->id < 1) {
            throw EntitlementException::invalidEntitlementId($entitlement->id);
        }

        $current = $this->find($entitlement->id);
        if ($current === null) {
            throw EntitlementException::entitlementNotFound($entitlement->id);
        }

        if ($current->snapshotSignature() !== $entitlement->snapshotSignature()) {
            throw EntitlementException::snapshotImmutableConflict();
        }

        if ($current->sourceOrderItemId !== $entitlement->sourceOrderItemId) {
            if (
                $entitlement->sourceOrderItemId !== null
                && isset($this->sourceOrderItemIndex[$entitlement->sourceOrderItemId])
                && $this->sourceOrderItemIndex[$entitlement->sourceOrderItemId] !== $entitlement->id
            ) {
                throw EntitlementException::duplicateSourceOrderItem($entitlement->sourceOrderItemId);
            }
        }

        if ($current->sourceOrderItemId !== null) {
            unset($this->sourceOrderItemIndex[$current->sourceOrderItemId]);
        }

        $this->entitlements[$entitlement->id] = $entitlement;
        if ($entitlement->sourceOrderItemId !== null) {
            $this->sourceOrderItemIndex[$entitlement->sourceOrderItemId] = $entitlement->id;
        }

        return $entitlement;
    }

    public function find(int $id): ?Entitlement
    {
        return $this->entitlements[$id] ?? null;
    }

    /**
     * @return list<Entitlement>
     */
    public function forUser(int $userId): array
    {
        $items = array_values(array_filter(
            $this->entitlements,
            static fn (Entitlement $entitlement): bool => $entitlement->userId === $userId,
        ));

        usort($items, static fn (Entitlement $left, Entitlement $right): int => $left->id <=> $right->id);

        return $items;
    }

    public function findBySourceOrderItem(int $sourceOrderItemId): ?Entitlement
    {
        $id = $this->sourceOrderItemIndex[$sourceOrderItemId] ?? null;
        if ($id === null) {
            return null;
        }

        return $this->entitlements[$id] ?? null;
    }

    public function currentForUserResource(int $userId, int $resourceId, string $atUtc): ?Entitlement
    {
        $candidates = array_values(array_filter(
            $this->entitlements,
            static fn (Entitlement $entitlement): bool => (
                $entitlement->userId === $userId
                && $entitlement->resourceId === $resourceId
                && $entitlement->isActive($atUtc)
            ),
        ));

        if (count($candidates) === 0) {
            return null;
        }

        usort(
            $candidates,
            static function (Entitlement $left, Entitlement $right): int {
                if ($left->priority !== $right->priority) {
                    return $right->priority <=> $left->priority;
                }

                return $left->id <=> $right->id;
            },
        );

        return $candidates[0];
    }

    private function nextId(): int
    {
        if (count($this->entitlements) === 0) {
            return 1;
        }

        return max(array_keys($this->entitlements)) + 1;
    }
}
