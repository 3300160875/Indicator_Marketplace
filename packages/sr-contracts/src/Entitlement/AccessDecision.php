<?php
declare(strict_types=1);

namespace StockResource\Contracts\Entitlement;

use InvalidArgumentException;

final readonly class AccessDecision
{
    private const SOURCES = ['FREE', 'PURCHASE', 'MANUAL', 'VIP', 'NONE'];

    /**
     * @param array<string, mixed>|null $quota
     */
    public function __construct(
        public bool $allowed,
        public string $reasonCode,
        public string $source,
        public ?int $entitlementId = null,
        public ?string $grantType = null,
        public ?array $quota = null,
        public ?string $expiresAt = null,
        public ?string $rulesVersion = null,
    ) {
        if (trim($this->reasonCode) === '') {
            throw new InvalidArgumentException('reason_code is required.');
        }

        if (! in_array($this->source, self::SOURCES, true)) {
            throw new InvalidArgumentException('source is not supported.');
        }

        if ($this->entitlementId !== null && $this->entitlementId < 1) {
            throw new InvalidArgumentException('entitlement_id must be null or positive.');
        }
    }

    /**
     * @param array<string, mixed>|null $quota
     */
    public static function allow(
        string $reasonCode,
        string $source,
        ?int $entitlementId = null,
        ?string $grantType = null,
        ?array $quota = null,
        ?string $expiresAt = null,
        ?string $rulesVersion = null,
    ): self {
        return new self(
            allowed: true,
            reasonCode: $reasonCode,
            source: $source,
            entitlementId: $entitlementId,
            grantType: $grantType,
            quota: $quota,
            expiresAt: $expiresAt,
            rulesVersion: $rulesVersion,
        );
    }

    /**
     * @param array<string, mixed>|null $quota
     */
    public static function deny(
        string $reasonCode,
        string $source = 'NONE',
        ?int $entitlementId = null,
        ?string $grantType = null,
        ?array $quota = null,
        ?string $expiresAt = null,
        ?string $rulesVersion = null,
    ): self {
        return new self(
            allowed: false,
            reasonCode: $reasonCode,
            source: $source,
            entitlementId: $entitlementId,
            grantType: $grantType,
            quota: $quota,
            expiresAt: $expiresAt,
            rulesVersion: $rulesVersion,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason_code' => $this->reasonCode,
            'source' => $this->source,
            'entitlement_id' => $this->entitlementId,
            'grant_type' => $this->grantType,
            'quota' => $this->quota,
            'expires_at' => $this->expiresAt,
            'rules_version' => $this->rulesVersion,
        ];
    }
}
