<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Infrastructure\Repository;

interface EntitlementRepository
{
    public function create(Entitlement $entitlement): Entitlement;

    public function save(Entitlement $entitlement): Entitlement;

    public function find(int $id): ?Entitlement;

    /**
     * @return list<Entitlement>
     */
    public function forUser(int $userId): array;

    public function findBySourceOrderItem(int $sourceOrderItemId): ?Entitlement;

    /**
     * 返回用户在指定时刻对指定资源的当前有效权益。
     */
    public function currentForUserResource(int $userId, int $resourceId, string $atUtc): ?Entitlement;
}
