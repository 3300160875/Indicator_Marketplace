<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Application;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use StockResource\Entitlements\Infrastructure\Repository\Entitlement;
use StockResource\Entitlements\Infrastructure\Repository\EntitlementRepository;

final readonly class MembershipService
{
    private const SORT_ORDER = ['coverage', 'quota', 'priority', 'expires_at', 'id'];

    public function __construct(private EntitlementRepository $repository)
    {
    }

    /**
     * @param array{
     *     user_id: int,
     *     edd_customer_id?: int|null,
     *     source_order_id: int,
     *     source_order_item_id: int,
     *     plan_download_id: int,
     *     plan_code: string,
     *     duration_value: int,
     *     duration_unit: string,
     *     scope_type: string,
     *     scope_snapshot: array<string, mixed>,
     *     quota_snapshot?: array<string, mixed>|null,
     *     rules_version: string,
     *     priority: int,
     *     purchased_at: string,
     *     created_by?: int|null
     * } $request
     */
    public function createRenewalSegment(array $request): Entitlement
    {
        $normalized = $this->normalizeRenewalRequest($request);

        $startsAt = $this->renewalStartsAt(
            userId: $normalized['user_id'],
            planDownloadId: $normalized['plan_download_id'],
            purchasedAt: $normalized['purchased_at'],
        );
        $expiresAt = $this->addDuration($startsAt, $normalized['duration_value'], $normalized['duration_unit']);

        return $this->repository->create(Entitlement::fromSnapshot(
            userId: $normalized['user_id'],
            eddCustomerId: $normalized['edd_customer_id'],
            grantType: 'membership',
            sourceType: 'order_item',
            sourceOrderId: $normalized['source_order_id'],
            sourceOrderItemId: $normalized['source_order_item_id'],
            planDownloadId: $normalized['plan_download_id'],
            parentEntitlementId: null,
            resourceId: null,
            scopeType: $normalized['scope_type'],
            scopeSnapshot: $normalized['scope_snapshot'],
            quotaSnapshot: $normalized['quota_snapshot'],
            rulesVersion: $normalized['rules_version'],
            startsAt: $startsAt,
            expiresAt: $expiresAt,
            priority: $normalized['priority'],
            createdBy: $normalized['created_by'],
            createdAt: $normalized['purchased_at'],
            updatedAt: $normalized['purchased_at'],
        ));
    }

    /**
     * @param list<int> $taxonomyTermIds
     * @return array{
     *     entitlement: Entitlement|null,
     *     reason: array<string, mixed>,
     *     sort_order: list<string>
     * }
     */
    public function chooseBestForResource(
        int $userId,
        int $resourceId,
        array $taxonomyTermIds,
        string $atUtc,
    ): array {
        $this->assertPositive($userId, 'user_id');
        $this->assertPositive($resourceId, 'resource_id');
        $at = $this->parseUtc($atUtc);

        $candidates = [];
        foreach ($this->repository->forUser($userId) as $entitlement) {
            if (! in_array($entitlement->grantType, ['membership', 'vip'], true)) {
                continue;
            }

            if (! $entitlement->isActive($at->format(DateTimeInterface::ATOM))) {
                continue;
            }

            $coverage = $this->coverage($entitlement, $resourceId, $taxonomyTermIds);
            if ($coverage['rank'] < 1) {
                continue;
            }

            $quotaRemaining = $this->quotaRemaining($entitlement);
            if ($quotaRemaining < 1) {
                continue;
            }

            $candidates[] = [
                'entitlement' => $entitlement,
                'coverage_rank' => $coverage['rank'],
                'coverage_type' => $coverage['type'],
                'quota_remaining' => $quotaRemaining,
                'expiry_rank' => $this->expiryRank($entitlement->expiresAt),
            ];
        }

        if ($candidates === []) {
            return [
                'entitlement' => null,
                'reason' => ['code' => 'no_matching_membership'],
                'sort_order' => self::SORT_ORDER,
            ];
        }

        usort(
            $candidates,
            static function (array $left, array $right): int {
                foreach ([
                    'coverage_rank',
                    'quota_remaining',
                ] as $field) {
                    if ($left[$field] !== $right[$field]) {
                        return $right[$field] <=> $left[$field];
                    }
                }

                $leftEntitlement = $left['entitlement'];
                $rightEntitlement = $right['entitlement'];
                if ($leftEntitlement->priority !== $rightEntitlement->priority) {
                    return $rightEntitlement->priority <=> $leftEntitlement->priority;
                }

                if ($left['expiry_rank'] !== $right['expiry_rank']) {
                    return $right['expiry_rank'] <=> $left['expiry_rank'];
                }

                return $leftEntitlement->id <=> $rightEntitlement->id;
            },
        );

        $winner = $candidates[0];
        $entitlement = $winner['entitlement'];

        return [
            'entitlement' => $entitlement,
            'reason' => [
                'code' => 'membership_selected',
                'coverage_type' => $winner['coverage_type'],
                'quota_remaining' => $winner['quota_remaining'],
                'priority' => $entitlement->priority,
                'expires_at' => $entitlement->expiresAt,
            ],
            'sort_order' => self::SORT_ORDER,
        ];
    }

    private function renewalStartsAt(int $userId, int $planDownloadId, string $purchasedAt): string
    {
        $purchasedAtDate = $this->parseUtc($purchasedAt);
        $latestActiveExpiry = null;

        foreach ($this->repository->forUser($userId) as $entitlement) {
            if ($entitlement->grantType !== 'membership') {
                continue;
            }

            if ($entitlement->planDownloadId !== $planDownloadId) {
                continue;
            }

            if ($entitlement->expiresAt === null) {
                continue;
            }

            if (! $entitlement->isActive($purchasedAtDate->format(DateTimeInterface::ATOM))) {
                continue;
            }

            $expiresAt = $this->parseUtc($entitlement->expiresAt);
            if ($expiresAt <= $purchasedAtDate) {
                continue;
            }

            if ($latestActiveExpiry === null || $expiresAt > $latestActiveExpiry) {
                $latestActiveExpiry = $expiresAt;
            }
        }

        return ($latestActiveExpiry ?? $purchasedAtDate)->format(DateTimeInterface::ATOM);
    }

    private function addDuration(string $startsAt, int $value, string $unit): string
    {
        $this->assertPositive($value, 'duration_value');
        $unit = trim(strtolower($unit));
        $interval = match ($unit) {
            'day' => new DateInterval('P'.$value.'D'),
            'month' => new DateInterval('P'.$value.'M'),
            'year' => new DateInterval('P'.$value.'Y'),
            default => throw new InvalidArgumentException('Unsupported membership duration unit: '.$unit),
        };

        return $this->parseUtc($startsAt)->add($interval)->format(DateTimeInterface::ATOM);
    }

    /**
     * @param list<int> $taxonomyTermIds
     * @return array{rank: int, type: string}
     */
    private function coverage(Entitlement $entitlement, int $resourceId, array $taxonomyTermIds): array
    {
        $scope = $this->scopePayload($entitlement);
        $excluded = $this->positiveIntList($scope['excluded_resource_ids'] ?? []);
        if (in_array($resourceId, $excluded, true)) {
            return ['rank' => 0, 'type' => 'excluded'];
        }

        $type = (string) ($scope['type'] ?? $entitlement->scopeType);
        if ($type === 'resources') {
            $resourceIds = $this->positiveIntList($scope['resource_ids'] ?? $scope['resources'] ?? []);
            return in_array($resourceId, $resourceIds, true)
                ? ['rank' => 300, 'type' => 'resources']
                : ['rank' => 0, 'type' => 'resource_mismatch'];
        }

        if ($type === 'taxonomies') {
            $termIds = $this->positiveIntList($scope['taxonomy_term_ids'] ?? $scope['term_ids'] ?? $scope['category_ids'] ?? []);
            return array_values(array_intersect($taxonomyTermIds, $termIds)) !== []
                ? ['rank' => 200, 'type' => 'taxonomies']
                : ['rank' => 0, 'type' => 'taxonomy_mismatch'];
        }

        if ($type === 'all') {
            return ['rank' => 100, 'type' => 'all'];
        }

        return ['rank' => 0, 'type' => 'unknown'];
    }

    /**
     * @return array<string, mixed>
     */
    private function scopePayload(Entitlement $entitlement): array
    {
        $payload = $entitlement->scopeSnapshot;
        if (isset($payload['scope']) && is_array($payload['scope'])) {
            $payload = $payload['scope'];
        }

        if (isset($payload['rules']) && is_array($payload['rules'])) {
            $payload = array_replace($payload['rules'], $payload);
        }

        return $payload;
    }

    private function quotaRemaining(Entitlement $entitlement): int
    {
        if (! is_array($entitlement->quotaSnapshot)) {
            return 0;
        }

        if (array_key_exists('available', $entitlement->quotaSnapshot) && $entitlement->quotaSnapshot['available'] === false) {
            return 0;
        }

        if (array_key_exists('remaining', $entitlement->quotaSnapshot)) {
            return max(0, (int) $entitlement->quotaSnapshot['remaining']);
        }

        if (array_key_exists('limit', $entitlement->quotaSnapshot)) {
            return max(0, (int) $entitlement->quotaSnapshot['limit']);
        }

        return 0;
    }

    private function expiryRank(?string $expiresAt): int
    {
        if ($expiresAt === null) {
            return PHP_INT_MAX;
        }

        return $this->parseUtc($expiresAt)->getTimestamp();
    }

    /**
     * @return list<int>
     */
    private function positiveIntList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (is_numeric($value) && (int) $value > 0) {
                $ids[(string) ((int) $value)] = (int) $value;
            }
        }
        ksort($ids);

        return array_values($ids);
    }

    private function parseUtc(string $datetime): DateTimeImmutable
    {
        $date = new DateTimeImmutable($datetime);

        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    private function assertPositive(int $value, string $field): void
    {
        if ($value < 1) {
            throw new InvalidArgumentException($field.' must be positive.');
        }
    }

    /**
     * @param array<string, mixed> $request
     * @return array{
     *     user_id: int,
     *     edd_customer_id: int|null,
     *     source_order_id: int,
     *     source_order_item_id: int,
     *     plan_download_id: int,
     *     plan_code: string,
     *     duration_value: int,
     *     duration_unit: string,
     *     scope_type: string,
     *     scope_snapshot: array<string, mixed>,
     *     quota_snapshot: array<string, mixed>|null,
     *     rules_version: string,
     *     priority: int,
     *     purchased_at: string,
     *     created_by: int|null
     * }
     */
    private function normalizeRenewalRequest(array $request): array
    {
        foreach ([
            'user_id',
            'source_order_id',
            'source_order_item_id',
            'plan_download_id',
            'duration_value',
        ] as $field) {
            if (! isset($request[$field]) || ! is_numeric($request[$field]) || (int) $request[$field] < 1) {
                throw new InvalidArgumentException($field.' must be positive.');
            }
        }

        foreach ([
            'plan_code',
            'duration_unit',
            'scope_type',
            'rules_version',
            'purchased_at',
        ] as $field) {
            if (! isset($request[$field]) || ! is_string($request[$field]) || trim($request[$field]) === '') {
                throw new InvalidArgumentException($field.' is required.');
            }
        }

        foreach (['scope_snapshot'] as $field) {
            if (! isset($request[$field]) || ! is_array($request[$field])) {
                throw new InvalidArgumentException($field.' must be an array.');
            }
        }

        if (array_key_exists('quota_snapshot', $request) && $request['quota_snapshot'] !== null && ! is_array($request['quota_snapshot'])) {
            throw new InvalidArgumentException('quota_snapshot must be an array or null.');
        }

        $this->parseUtc($request['purchased_at']);

        return [
            'user_id' => (int) $request['user_id'],
            'edd_customer_id' => isset($request['edd_customer_id']) && is_numeric($request['edd_customer_id']) ? (int) $request['edd_customer_id'] : null,
            'source_order_id' => (int) $request['source_order_id'],
            'source_order_item_id' => (int) $request['source_order_item_id'],
            'plan_download_id' => (int) $request['plan_download_id'],
            'plan_code' => trim($request['plan_code']),
            'duration_value' => (int) $request['duration_value'],
            'duration_unit' => trim($request['duration_unit']),
            'scope_type' => trim($request['scope_type']),
            'scope_snapshot' => $request['scope_snapshot'],
            'quota_snapshot' => $request['quota_snapshot'] ?? null,
            'rules_version' => trim($request['rules_version']),
            'priority' => isset($request['priority']) && is_numeric($request['priority']) ? (int) $request['priority'] : 0,
            'purchased_at' => $this->parseUtc($request['purchased_at'])->format(DateTimeInterface::ATOM),
            'created_by' => isset($request['created_by']) && is_numeric($request['created_by']) ? (int) $request['created_by'] : null,
        ];
    }
}
