<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Rights;

final readonly class RightsRecord
{
    private const ALLOWED_STATUSES = [
        'pending',
        'approved',
        'rejected',
        'expired',
        'suspended',
        'taken_down',
        'cancelled',
    ];

    private const ALLOWED_SOURCE_TYPES = [
        'original',
        'licensed',
        'purchased',
        'open_source',
        'other',
    ];

    public function __construct(
        public int $id,
        public int $resourceId,
        public string $status,
        public string $sourceType,
        public ?string $rightsHolder,
        public ?string $licenseScope,
        public ?string $licenseReference,
        public ?string $evidenceStorageKey,
        public ?string $startsAt,
        public ?string $expiresAt,
        public ?int $reviewerId,
        public ?string $reviewedAt,
        public ?string $internalNote,
        public string $createdAt,
        public string $updatedAt,
    ) {
        if ($id <= 0 || $resourceId <= 0) {
            throw new RightsException('invalid_rights_record_id', 'Rights record and resource IDs must be positive.');
        }
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new RightsException('invalid_rights_status', 'Rights status is not supported.');
        }
        if (! in_array($sourceType, self::ALLOWED_SOURCE_TYPES, true)) {
            throw new RightsException('invalid_rights_source_type', 'Rights source type is not supported.');
        }
        if ($reviewerId !== null && $reviewerId <= 0) {
            throw new RightsException('invalid_reviewer_id', 'Reviewer ID must be positive when present.');
        }
        if ($evidenceStorageKey !== null) {
            (new RightsEvidencePolicy())->assertPrivateStorageKey($evidenceStorageKey);
        }
        foreach ([
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'reviewed_at' => $reviewedAt,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ] as $field => $value) {
            if ($value !== null && date_create_immutable($value) === false) {
                throw new RightsException('invalid_'.$field, $field.' must be an ISO-8601 datetime.');
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            resourceId: (int) ($data['resource_id'] ?? 0),
            status: trim((string) ($data['status'] ?? 'pending')),
            sourceType: trim((string) ($data['source_type'] ?? 'other')),
            rightsHolder: self::nullableString($data['rights_holder'] ?? null),
            licenseScope: self::nullableString($data['license_scope'] ?? null),
            licenseReference: self::nullableString($data['license_reference'] ?? null),
            evidenceStorageKey: self::nullableString($data['evidence_storage_key'] ?? null),
            startsAt: self::nullableString($data['starts_at'] ?? null),
            expiresAt: self::nullableString($data['expires_at'] ?? null),
            reviewerId: isset($data['reviewer_id']) ? (int) $data['reviewer_id'] : null,
            reviewedAt: self::nullableString($data['reviewed_at'] ?? null),
            internalNote: self::nullableString($data['internal_note'] ?? null),
            createdAt: trim((string) ($data['created_at'] ?? '')),
            updatedAt: trim((string) ($data['updated_at'] ?? '')),
        );
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function hasStartedAt(string $nowUtc): bool
    {
        $now = self::date($nowUtc);

        if ($this->startsAt !== null && self::date($this->startsAt) > $now) {
            return false;
        }

        return true;
    }

    public function isExpiredAt(string $nowUtc): bool
    {
        $now = self::date($nowUtc);

        if ($this->expiresAt !== null && self::date($this->expiresAt) <= $now) {
            return true;
        }

        return false;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function date(string $value): \DateTimeImmutable
    {
        $date = date_create_immutable($value);
        if (! $date instanceof \DateTimeImmutable) {
            throw new RightsException('invalid_datetime', 'Datetime must be valid.');
        }

        return $date;
    }
}
