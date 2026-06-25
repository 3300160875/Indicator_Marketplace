<?php
declare(strict_types=1);

namespace StockResource\Contracts\Dto;

use StockResource\Contracts\Exception\ValidationException;
use StockResource\Contracts\Value\PositiveId;
use StockResource\Contracts\Value\UtcDateTime;

final readonly class EntitlementDto
{
    private const GRANT_TYPES = ['resource', 'membership', 'manual'];
    private const STATUSES = ['pending', 'active', 'expired', 'revoked', 'suspended'];

    /**
     * @param array<string,mixed> $scope
     * @param array<string,mixed>|null $quota
     */
    public function __construct(
        public PositiveId $id,
        public string $grantType,
        public string $status,
        public UtcDateTime $startsAt,
        public ?UtcDateTime $expiresAt,
        public array $scope,
        public ?array $quota,
        public string $rulesVersion,
        public ?PositiveId $sourceOrderId,
    ) {
        if (! in_array($grantType, self::GRANT_TYPES, true)) {
            throw new ValidationException('Entitlement grant type is unknown.');
        }
        if (! in_array($status, self::STATUSES, true)) {
            throw new ValidationException('Entitlement status is unknown.');
        }
        if ('' === trim($rulesVersion)) {
            throw new ValidationException('Entitlement rules version must not be empty.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'grant_type' => $this->grantType,
            'status' => $this->status,
            'starts_at' => $this->startsAt->toString(),
            'expires_at' => $this->expiresAt?->toString(),
            'scope' => $this->scope,
            'quota' => $this->quota,
            'rules_version' => $this->rulesVersion,
            'source_order_id' => $this->sourceOrderId?->toInt(),
        ];
    }
}
