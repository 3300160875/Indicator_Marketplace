<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Infrastructure\Repository;

use DateTimeImmutable;
use DateTimeInterface;

final readonly class Entitlement
{
    private string $scopeSnapshotCanonical;
    private ?string $quotaSnapshotCanonical;

    public function __construct(
        public int $id,
        public int $userId,
        public ?int $eddCustomerId,
        public string $grantType,
        public string $sourceType,
        public ?int $sourceOrderId,
        public ?int $sourceOrderItemId,
        public ?int $planDownloadId,
        public ?int $parentEntitlementId,
        public ?int $resourceId,
        public EntitlementStatus $status,
        public string $startsAt,
        public ?string $expiresAt,
        public string $scopeType,
        public array $scopeSnapshot,
        public ?array $quotaSnapshot,
        public string $rulesVersion,
        public int $priority,
        public ?int $createdBy,
        public ?string $revokedAt,
        public ?int $revokedBy,
        public ?string $revokeReason,
        public string $createdAt,
        public string $updatedAt,
    ) {
        if ($this->id < 0) {
            throw EntitlementException::idMustBeNew();
        }
        if ($this->userId < 1) {
            throw EntitlementException::invalidUserId($this->userId);
        }
        $this->scopeSnapshotCanonical = self::canonicalJson($this->scopeSnapshot);
        $this->quotaSnapshotCanonical = is_array($this->quotaSnapshot)
            ? self::canonicalJson($this->quotaSnapshot)
            : null;

        $this->assertTemporal($this->startsAt, 'starts_at');
        if ($this->expiresAt !== null) {
            $this->assertTemporal($this->expiresAt, 'expires_at');
        }
        if (trim($this->rulesVersion) === '') {
            throw new EntitlementException('missing_rules_version', 'rules_version is required.');
        }
    }

    public static function fromSnapshot(
        int $userId,
        ?int $eddCustomerId,
        string $grantType,
        string $sourceType,
        ?int $sourceOrderId,
        ?int $sourceOrderItemId,
        ?int $planDownloadId,
        ?int $parentEntitlementId,
        ?int $resourceId,
        string $scopeType,
        array $scopeSnapshot,
        ?array $quotaSnapshot,
        string $rulesVersion,
        string $startsAt,
        ?string $expiresAt,
        int $priority,
        ?int $createdBy,
        string $createdAt,
        string $updatedAt,
    ): self {
        return new self(
            id: 0,
            userId: $userId,
            eddCustomerId: $eddCustomerId,
            grantType: $grantType,
            sourceType: $sourceType,
            sourceOrderId: $sourceOrderId,
            sourceOrderItemId: $sourceOrderItemId,
            planDownloadId: $planDownloadId,
            parentEntitlementId: $parentEntitlementId,
            resourceId: $resourceId,
            status: EntitlementStatus::Active,
            startsAt: $startsAt,
            expiresAt: $expiresAt,
            scopeType: $scopeType,
            scopeSnapshot: $scopeSnapshot,
            quotaSnapshot: $quotaSnapshot,
            rulesVersion: $rulesVersion,
            priority: $priority,
            createdBy: $createdBy,
            revokedAt: null,
            revokedBy: null,
            revokeReason: null,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            id: $id,
            userId: $this->userId,
            eddCustomerId: $this->eddCustomerId,
            grantType: $this->grantType,
            sourceType: $this->sourceType,
            sourceOrderId: $this->sourceOrderId,
            sourceOrderItemId: $this->sourceOrderItemId,
            planDownloadId: $this->planDownloadId,
            parentEntitlementId: $this->parentEntitlementId,
            resourceId: $this->resourceId,
            status: $this->status,
            startsAt: $this->startsAt,
            expiresAt: $this->expiresAt,
            scopeType: $this->scopeType,
            scopeSnapshot: $this->scopeSnapshot,
            quotaSnapshot: $this->quotaSnapshot,
            rulesVersion: $this->rulesVersion,
            priority: $this->priority,
            createdBy: $this->createdBy,
            revokedAt: $this->revokedAt,
            revokedBy: $this->revokedBy,
            revokeReason: $this->revokeReason,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }

    public function revoke(string $revokedAt, int $revokedBy, string $reason): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            eddCustomerId: $this->eddCustomerId,
            grantType: $this->grantType,
            sourceType: $this->sourceType,
            sourceOrderId: $this->sourceOrderId,
            sourceOrderItemId: $this->sourceOrderItemId,
            planDownloadId: $this->planDownloadId,
            parentEntitlementId: $this->parentEntitlementId,
            resourceId: $this->resourceId,
            status: EntitlementStatus::Revoked,
            startsAt: $this->startsAt,
            expiresAt: $this->expiresAt,
            scopeType: $this->scopeType,
            scopeSnapshot: $this->scopeSnapshot,
            quotaSnapshot: $this->quotaSnapshot,
            rulesVersion: $this->rulesVersion,
            priority: $this->priority,
            createdBy: $this->createdBy,
            revokedAt: $revokedAt,
            revokedBy: $revokedBy,
            revokeReason: trim($reason),
            createdAt: $this->createdAt,
            updatedAt: $revokedAt,
        );
    }

    public function isActive(string $atUtc): bool
    {
        if ($this->status !== EntitlementStatus::Active) {
            return false;
        }

        $now = self::parseUtc($atUtc);
        $starts = self::parseUtc($this->startsAt);
        $expires = $this->expiresAt === null ? null : self::parseUtc($this->expiresAt);

        if ($now === false || $starts === false || ($expires !== null && $expires === false)) {
            return false;
        }

        if ($now < $starts) {
            return false;
        }

        if ($expires !== null && $now >= $expires) {
            return false;
        }

        return $this->revokedAt === null;
    }

    public function snapshotSignature(): string
    {
        return md5($this->scopeSnapshotCanonical . '|' . ($this->quotaSnapshotCanonical ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'edd_customer_id' => $this->eddCustomerId,
            'grant_type' => $this->grantType,
            'source_type' => $this->sourceType,
            'source_order_id' => $this->sourceOrderId,
            'source_order_item_id' => $this->sourceOrderItemId,
            'plan_download_id' => $this->planDownloadId,
            'parent_entitlement_id' => $this->parentEntitlementId,
            'resource_id' => $this->resourceId,
            'status' => $this->status->value,
            'starts_at' => $this->startsAt,
            'expires_at' => $this->expiresAt,
            'scope_type' => $this->scopeType,
            'scope_snapshot' => $this->scopeSnapshot,
            'quota_snapshot' => $this->quotaSnapshot,
            'rules_version' => $this->rulesVersion,
            'priority' => $this->priority,
            'created_by' => $this->createdBy,
            'revoked_at' => $this->revokedAt,
            'revoked_by' => $this->revokedBy,
            'revoke_reason' => $this->revokeReason,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    private static function assertTemporal(string $datetime, string $field): void
    {
        if ($datetime === '') {
            throw EntitlementException::invalidTimeField($field);
        }

        if (self::parseUtc($datetime) === false) {
            throw EntitlementException::invalidTimeField($field);
        }
    }

    private static function parseUtc(string $datetime): false | DateTimeImmutable
    {
        $utc = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $datetime);
        if ($utc !== false) {
            return $utc->setTimezone(new \DateTimeZone('UTC'));
        }

        return false;
    }

    private static function canonicalJson(array $value): string
    {
        $normalized = $value;
        self::sortRecursive($normalized);

        return json_encode($normalized, JSON_THROW_ON_ERROR);
    }

    private static function sortRecursive(array &$value): void
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                self::sortRecursive($item);
            }
        }
    }
}
