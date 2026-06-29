<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Integration;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use StockResource\Contracts\Dto\OrderCompletedEvent;
use StockResource\Core\Commerce\OrderSnapshot\OrderItemBusinessSnapshot;
use StockResource\Entitlements\Infrastructure\Repository\Entitlement;
use StockResource\Entitlements\Infrastructure\Repository\EntitlementException;
use StockResource\Entitlements\Infrastructure\Repository\EntitlementRepository;
use Throwable;

final readonly class EddOrderListener
{
    private const COMPLETED_PURCHASE_HOOK = 'edd_complete_purchase';

    public function __construct(private EntitlementRepository $repository)
    {
    }

    public function registerHooks(object $runtime, callable $completionResolver, int $priority = 10): void
    {
        if (! method_exists($runtime, 'addAction')) {
            throw new \InvalidArgumentException('Runtime must provide addAction().');
        }

        $runtime->addAction(
            self::COMPLETED_PURCHASE_HOOK,
            fn (int $orderId): array => $this->handleCompletedOrderId($orderId, $completionResolver),
            $priority,
            1,
        );
    }

    /**
     * @return array{created: list<Entitlement>, reused: list<Entitlement>, failed: list<array{order_item_id: int, code: string, message: string}>}
     */
    public function handleCompletedOrderId(int $orderId, callable $completionResolver): array
    {
        if ($orderId < 1) {
            return [
                'created' => [],
                'reused' => [],
                'failed' => [$this->failure(0, 'invalid_order_id', 'Completed order id must be positive.')],
            ];
        }

        $payload = $completionResolver($orderId);
        if (! is_array($payload) || ! ($payload['event'] ?? null) instanceof OrderCompletedEvent) {
            return [
                'created' => [],
                'reused' => [],
                'failed' => [$this->failure(0, 'invalid_completion_payload', 'Completion resolver must return event and snapshots.')],
            ];
        }

        return $this->handle($payload['event'], $payload['snapshots'] ?? []);
    }

    /**
     * @param iterable<OrderItemBusinessSnapshot> $snapshots
     * @return array{created: list<Entitlement>, reused: list<Entitlement>, failed: list<array{order_item_id: int, code: string, message: string}>}
     */
    public function handle(OrderCompletedEvent $event, iterable $snapshots): array
    {
        $indexed = $this->indexSnapshots($snapshots);
        $created = [];
        $reused = [];
        $failed = [];

        foreach ($event->orderItemIds as $orderItemId) {
            $sourceOrderItemId = $orderItemId->toInt();
            $existing = $this->repository->findBySourceOrderItem($sourceOrderItemId);
            if ($existing !== null) {
                $reused[] = $existing;
                continue;
            }

            $snapshot = $indexed[$sourceOrderItemId] ?? null;
            if (! $snapshot instanceof OrderItemBusinessSnapshot) {
                $failed[] = $this->failure($sourceOrderItemId, 'missing_snapshot', 'Order item business snapshot is required.');
                continue;
            }

            try {
                $created[] = $this->repository->create($this->entitlementFromSnapshot($event, $snapshot));
            } catch (EntitlementException $exception) {
                if ($exception->codeName === 'duplicate_source_order_item_id') {
                    $raceWinner = $this->repository->findBySourceOrderItem($sourceOrderItemId);
                    if ($raceWinner !== null) {
                        $reused[] = $raceWinner;
                        continue;
                    }
                }

                $failed[] = $this->failure($sourceOrderItemId, $exception->codeName, $exception->getMessage());
            } catch (Throwable $exception) {
                $failed[] = $this->failure($sourceOrderItemId, 'invalid_order_item_snapshot', $exception->getMessage());
            }
        }

        return [
            'created' => $created,
            'reused' => $reused,
            'failed' => $failed,
        ];
    }

    /**
     * @param iterable<OrderItemBusinessSnapshot> $snapshots
     * @return array<int, OrderItemBusinessSnapshot>
     */
    private function indexSnapshots(iterable $snapshots): array
    {
        $indexed = [];
        foreach ($snapshots as $snapshot) {
            if ($snapshot instanceof OrderItemBusinessSnapshot) {
                $indexed[$snapshot->orderItemId] = $snapshot;
            }
        }

        return $indexed;
    }

    private function entitlementFromSnapshot(OrderCompletedEvent $event, OrderItemBusinessSnapshot $snapshot): Entitlement
    {
        $this->assertEventMatchesSnapshot($event, $snapshot);

        return match ($snapshot->productType) {
            'resource' => $this->resourceEntitlement($snapshot),
            'membership_plan' => $this->membershipEntitlement($snapshot),
            default => throw new \InvalidArgumentException('Unsupported product type: '.$snapshot->productType),
        };
    }

    private function resourceEntitlement(OrderItemBusinessSnapshot $snapshot): Entitlement
    {
        if ($snapshot->resourceId === null || $snapshot->versionId === null) {
            throw new \InvalidArgumentException('resource_id and version_id are required for resource order items.');
        }

        $createdAt = $this->atom($snapshot->capturedAt);

        return Entitlement::fromSnapshot(
            userId: $snapshot->userId,
            eddCustomerId: $snapshot->customerId,
            grantType: 'resource',
            sourceType: 'order_item',
            sourceOrderId: $snapshot->orderId,
            sourceOrderItemId: $snapshot->orderItemId,
            planDownloadId: null,
            parentEntitlementId: null,
            resourceId: $snapshot->resourceId,
            scopeType: 'resources',
            scopeSnapshot: [
                'type' => 'resources',
                'resource_ids' => [$snapshot->resourceId],
                'version_id' => $snapshot->versionId,
                'download_id' => $snapshot->downloadId,
                'access_mode' => $snapshot->accessMode,
            ],
            quotaSnapshot: null,
            rulesVersion: $snapshot->rulesVersion,
            startsAt: $createdAt,
            expiresAt: null,
            priority: 10,
            createdBy: $snapshot->userId,
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );
    }

    private function membershipEntitlement(OrderItemBusinessSnapshot $snapshot): Entitlement
    {
        if ($snapshot->planDownloadId === null || $snapshot->planCode === null || trim($snapshot->planCode) === '') {
            throw new \InvalidArgumentException('plan_download_id and plan_code are required for membership order items.');
        }

        $createdAt = $this->atom($snapshot->capturedAt);
        $duration = $this->durationSnapshot($snapshot->termsSnapshot);
        $scope = $this->scopeTermsSnapshot($snapshot->termsSnapshot);
        $quota = $this->quotaTermsSnapshot($snapshot->termsSnapshot);
        $scopeSnapshot = $this->membershipScopeSnapshot($scope, $snapshot->planCode);
        $priority = max(0, (int) ($snapshot->termsSnapshot['priority'] ?? $snapshot->termsSnapshot['_sr_priority'] ?? 100));

        return Entitlement::fromSnapshot(
            userId: $snapshot->userId,
            eddCustomerId: $snapshot->customerId,
            grantType: 'membership',
            sourceType: 'order_item',
            sourceOrderId: $snapshot->orderId,
            sourceOrderItemId: $snapshot->orderItemId,
            planDownloadId: $snapshot->planDownloadId,
            parentEntitlementId: null,
            resourceId: null,
            scopeType: (string) $scopeSnapshot['type'],
            scopeSnapshot: $scopeSnapshot,
            quotaSnapshot: $quota,
            rulesVersion: $snapshot->rulesVersion,
            startsAt: $createdAt,
            expiresAt: $this->expiresAt($createdAt, $duration),
            priority: $priority,
            createdBy: $snapshot->userId,
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );
    }

    private function assertEventMatchesSnapshot(OrderCompletedEvent $event, OrderItemBusinessSnapshot $snapshot): void
    {
        if ($event->orderId->toInt() !== $snapshot->orderId) {
            throw new \InvalidArgumentException('Order snapshot does not match completed order id.');
        }

        if ($event->customerId->toInt() !== $snapshot->customerId) {
            throw new \InvalidArgumentException('Order snapshot does not match completed customer id.');
        }
    }

    /**
     * @param array<string, mixed> $scope
     * @return array<string, mixed>
     */
    private function membershipScopeSnapshot(array $scope, string $planCode): array
    {
        $type = trim((string) ($scope['type'] ?? ''));
        if ($type === '') {
            throw new \InvalidArgumentException('membership scope type is required.');
        }

        $rules = $this->arrayValue($scope, 'rules', false);
        $snapshot = [
            'type' => $type,
            'plan_code' => $planCode,
            'excluded_resource_ids' => $this->positiveIntList($scope['excluded_resource_ids'] ?? []),
        ];

        foreach (['resource_ids', 'resources', 'taxonomy_term_ids', 'term_ids', 'category_ids'] as $key) {
            if (array_key_exists($key, $rules)) {
                $snapshot[$key] = $this->positiveIntList($rules[$key]);
            }
        }

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $termsSnapshot
     * @return array{value: int, unit: string}
     */
    private function durationSnapshot(array $termsSnapshot): array
    {
        $nested = $this->arrayValue($termsSnapshot, 'duration', false);
        if ($nested !== []) {
            return [
                'value' => (int) ($nested['value'] ?? 0),
                'unit' => trim(strtolower((string) ($nested['unit'] ?? ''))),
            ];
        }

        return [
            'value' => (int) ($termsSnapshot['duration_value'] ?? $termsSnapshot['_sr_duration_value'] ?? 0),
            'unit' => trim(strtolower((string) ($termsSnapshot['duration_unit'] ?? $termsSnapshot['_sr_duration_unit'] ?? ''))),
        ];
    }

    /**
     * @param array<string, mixed> $termsSnapshot
     * @return array<string, mixed>
     */
    private function scopeTermsSnapshot(array $termsSnapshot): array
    {
        $nested = $this->arrayValue($termsSnapshot, 'scope', false);
        if ($nested !== []) {
            return $nested;
        }

        return [
            'type' => trim((string) ($termsSnapshot['scope_type'] ?? $termsSnapshot['_sr_scope_type'] ?? '')),
            'rules' => $this->decodedArray($termsSnapshot['scope_rules_json'] ?? $termsSnapshot['_sr_scope_rules_json'] ?? []),
            'excluded_resource_ids' => $this->decodedArray(
                $termsSnapshot['excluded_resource_ids'] ?? $termsSnapshot['_sr_excluded_resource_ids'] ?? [],
            ),
        ];
    }

    /**
     * @param array<string, mixed> $termsSnapshot
     * @return array<string, mixed>
     */
    private function quotaTermsSnapshot(array $termsSnapshot): array
    {
        $nested = $this->arrayValue($termsSnapshot, 'quota', false);
        if ($nested !== []) {
            return $nested;
        }

        $period = trim((string) ($termsSnapshot['quota_period'] ?? $termsSnapshot['_sr_quota_period'] ?? ''));
        $limit = (int) ($termsSnapshot['quota_limit'] ?? $termsSnapshot['_sr_quota_limit'] ?? 0);
        $redownloadPolicy = trim((string) (
            $termsSnapshot['redownload_policy']
            ?? $termsSnapshot['quota_redownload_policy']
            ?? $termsSnapshot['_sr_redownload_policy']
            ?? ''
        ));
        if ($period === '' || $limit < 1 || $redownloadPolicy === '') {
            throw new \InvalidArgumentException('quota period, limit and redownload policy are required.');
        }

        return [
            'period' => $period,
            'limit' => $limit,
            'redownload_policy' => $redownloadPolicy,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function arrayValue(array $payload, string $key, bool $required = true): array
    {
        $value = $payload[$key] ?? null;
        if (is_array($value)) {
            return $value;
        }

        if ($required) {
            throw new \InvalidArgumentException($key.' snapshot is required.');
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private function positiveIntList(mixed $raw): array
    {
        $raw = $this->decodedArray($raw);
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (is_numeric($value) && (int) $value > 0) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<mixed>
     */
    private function decodedArray(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $duration
     */
    private function expiresAt(string $startsAt, array $duration): ?string
    {
        $value = (int) ($duration['value'] ?? 0);
        $unit = trim((string) ($duration['unit'] ?? ''));
        if ($value < 1 || $unit === '') {
            throw new \InvalidArgumentException('membership duration value and unit are required.');
        }

        $interval = match ($unit) {
            'day' => new DateInterval('P'.$value.'D'),
            'month' => new DateInterval('P'.$value.'M'),
            'year' => new DateInterval('P'.$value.'Y'),
            default => throw new \InvalidArgumentException('Unsupported membership duration unit: '.$unit),
        };

        return (new DateTimeImmutable($startsAt))->add($interval)->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM);
    }

    private function atom(string $datetime): string
    {
        return (new DateTimeImmutable($datetime))->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM);
    }

    /**
     * @return array{order_item_id: int, code: string, message: string}
     */
    private function failure(int $orderItemId, string $code, string $message): array
    {
        return [
            'order_item_id' => $orderItemId,
            'code' => $code,
            'message' => $message,
        ];
    }
}
