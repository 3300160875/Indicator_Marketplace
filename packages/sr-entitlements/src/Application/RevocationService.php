<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Application;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use StockResource\Contracts\Dto\OrderRefundedEvent;
use StockResource\Entitlements\Infrastructure\Repository\Entitlement;
use StockResource\Entitlements\Infrastructure\Repository\EntitlementRepository;
use StockResource\Entitlements\Infrastructure\Repository\EntitlementStatus;

final readonly class RevocationService
{
    private mixed $auditSink;
    private mixed $cacheInvalidator;

    public function __construct(
        private EntitlementRepository $repository,
        mixed $auditSink = null,
        mixed $cacheInvalidator = null,
    ) {
        $this->auditSink = $auditSink;
        $this->cacheInvalidator = $cacheInvalidator;
    }

    /**
     * @return array{
     *     revoked: list<Entitlement>,
     *     already_revoked: list<Entitlement>,
     *     not_found: list<int>
     * }
     */
    public function handleRefundedOrder(OrderRefundedEvent $event): array
    {
        $revoked = [];
        $alreadyRevoked = [];
        $notFound = [];
        $revokedAt = $this->atom($event->refundedAt->toString());
        $orderId = $event->orderId->toInt();

        foreach ($event->refundedOrderItemIds as $orderItemId) {
            $sourceOrderItemId = $orderItemId->toInt();
            $entitlement = $this->repository->findBySourceOrderItem($sourceOrderItemId);
            if ($entitlement === null) {
                $notFound[] = $sourceOrderItemId;
                continue;
            }

            if ($entitlement->status === EntitlementStatus::Revoked || $entitlement->revokedAt !== null) {
                $alreadyRevoked[] = $entitlement;
                $this->invalidate($entitlement);
                continue;
            }

            $reason = 'refund:order:'.$orderId.':item:'.$sourceOrderItemId;
            $updated = $this->repository->save($entitlement->revoke($revokedAt, 0, $reason));
            $revoked[] = $updated;
            $this->audit('refund_revoke', $updated, 0, $reason, $revokedAt, [
                'order_id' => $orderId,
                'source_order_item_id' => $sourceOrderItemId,
                'full_refund' => $event->fullRefund,
            ]);
            $this->invalidate($updated);
        }

        return [
            'revoked' => $revoked,
            'already_revoked' => $alreadyRevoked,
            'not_found' => $notFound,
        ];
    }

    /**
     * @param array{
     *     user_id: int,
     *     resource_id?: int|null,
     *     plan_download_id?: int|null,
     *     scope_type: string,
     *     scope_snapshot: array<string, mixed>,
     *     quota_snapshot?: array<string, mixed>|null,
     *     rules_version: string,
     *     starts_at: string,
     *     expires_at?: string|null,
     *     priority?: int,
     *     actor_id: int,
     *     reason: string
     * } $request
     */
    public function grantManual(array $request): Entitlement
    {
        $normalized = $this->normalizeManualGrant($request);

        $entitlement = $this->repository->create(Entitlement::fromSnapshot(
            userId: $normalized['user_id'],
            eddCustomerId: null,
            grantType: 'manual',
            sourceType: 'manual',
            sourceOrderId: null,
            sourceOrderItemId: null,
            planDownloadId: $normalized['plan_download_id'],
            parentEntitlementId: null,
            resourceId: $normalized['resource_id'],
            scopeType: $normalized['scope_type'],
            scopeSnapshot: $normalized['scope_snapshot'],
            quotaSnapshot: $normalized['quota_snapshot'],
            rulesVersion: $normalized['rules_version'],
            startsAt: $normalized['starts_at'],
            expiresAt: $normalized['expires_at'],
            priority: $normalized['priority'],
            createdBy: $normalized['actor_id'],
            createdAt: $normalized['starts_at'],
            updatedAt: $normalized['starts_at'],
        ));

        $this->audit('manual_grant', $entitlement, $normalized['actor_id'], $normalized['reason'], $normalized['starts_at']);
        $this->invalidate($entitlement);

        return $entitlement;
    }

    public function revokeManual(int $entitlementId, int $actorId, string $reason, string $revokedAt): Entitlement
    {
        $this->assertPositive($entitlementId, 'entitlement_id');
        $this->assertPositive($actorId, 'actor_id');
        $reason = $this->requiredReason($reason);
        $revokedAt = $this->atom($revokedAt);

        $entitlement = $this->repository->find($entitlementId);
        if ($entitlement === null) {
            throw new InvalidArgumentException('entitlement not found.');
        }

        if ($entitlement->status === EntitlementStatus::Revoked || $entitlement->revokedAt !== null) {
            return $entitlement;
        }

        $updated = $this->repository->save($entitlement->revoke($revokedAt, $actorId, 'manual:'.$reason));
        $this->audit('manual_revoke', $updated, $actorId, $reason, $revokedAt);
        $this->invalidate($updated);

        return $updated;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function audit(string $action, Entitlement $entitlement, int $actorId, string $reason, string $occurredAt, array $extra = []): void
    {
        if (! is_callable($this->auditSink)) {
            return;
        }

        ($this->auditSink)(array_replace([
            'action' => $action,
            'entitlement_id' => $entitlement->id,
            'user_id' => $entitlement->userId,
            'actor_id' => $actorId,
            'reason' => $reason,
            'occurred_at' => $occurredAt,
        ], $extra));
    }

    private function invalidate(Entitlement $entitlement): void
    {
        if (! is_callable($this->cacheInvalidator)) {
            return;
        }

        $keys = [
            'user:'.$entitlement->userId.':entitlements',
            'user:'.$entitlement->userId.':download_tokens',
        ];

        if ($entitlement->resourceId !== null) {
            $keys[] = 'resource:'.$entitlement->resourceId.':access';
        }

        ($this->cacheInvalidator)($keys);
    }

    /**
     * @param array<string, mixed> $request
     * @return array{
     *     user_id: int,
     *     resource_id: int|null,
     *     plan_download_id: int|null,
     *     scope_type: string,
     *     scope_snapshot: array<string, mixed>,
     *     quota_snapshot: array<string, mixed>|null,
     *     rules_version: string,
     *     starts_at: string,
     *     expires_at: string|null,
     *     priority: int,
     *     actor_id: int,
     *     reason: string
     * }
     */
    private function normalizeManualGrant(array $request): array
    {
        foreach (['user_id', 'actor_id'] as $field) {
            if (! isset($request[$field]) || ! is_numeric($request[$field]) || (int) $request[$field] < 1) {
                throw new InvalidArgumentException($field.' must be positive.');
            }
        }

        foreach (['scope_type', 'rules_version', 'starts_at'] as $field) {
            if (! isset($request[$field]) || ! is_string($request[$field]) || trim($request[$field]) === '') {
                throw new InvalidArgumentException($field.' is required.');
            }
        }

        if (! isset($request['scope_snapshot']) || ! is_array($request['scope_snapshot'])) {
            throw new InvalidArgumentException('scope_snapshot must be an array.');
        }

        if (array_key_exists('quota_snapshot', $request) && $request['quota_snapshot'] !== null && ! is_array($request['quota_snapshot'])) {
            throw new InvalidArgumentException('quota_snapshot must be an array or null.');
        }

        $startsAt = $this->atom($request['starts_at']);
        $expiresAt = isset($request['expires_at']) && $request['expires_at'] !== null ? $this->atom((string) $request['expires_at']) : null;
        if ($expiresAt !== null && new DateTimeImmutable($expiresAt) <= new DateTimeImmutable($startsAt)) {
            throw new InvalidArgumentException('expires_at must be later than starts_at.');
        }

        $resourceId = $this->optionalPositiveInt($request, 'resource_id');
        $planDownloadId = $this->optionalPositiveInt($request, 'plan_download_id');

        return [
            'user_id' => (int) $request['user_id'],
            'resource_id' => $resourceId,
            'plan_download_id' => $planDownloadId,
            'scope_type' => trim($request['scope_type']),
            'scope_snapshot' => $request['scope_snapshot'],
            'quota_snapshot' => $request['quota_snapshot'] ?? null,
            'rules_version' => trim($request['rules_version']),
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'priority' => isset($request['priority']) && is_numeric($request['priority']) ? (int) $request['priority'] : 0,
            'actor_id' => (int) $request['actor_id'],
            'reason' => $this->requiredReason((string) ($request['reason'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $request
     */
    private function optionalPositiveInt(array $request, string $field): ?int
    {
        if (! array_key_exists($field, $request) || $request[$field] === null) {
            return null;
        }

        if (! is_numeric($request[$field]) || (int) $request[$field] < 1) {
            throw new InvalidArgumentException($field.' must be positive when provided.');
        }

        return (int) $request[$field];
    }

    private function requiredReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('reason is required.');
        }

        return $reason;
    }

    private function assertPositive(int $value, string $field): void
    {
        if ($value < 1) {
            throw new InvalidArgumentException($field.' must be positive.');
        }
    }

    private function atom(string $datetime): string
    {
        $date = new DateTimeImmutable($datetime);

        return $date->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
    }
}
