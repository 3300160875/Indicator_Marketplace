<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Rights;

final readonly class RightsPublicationRequest
{
    private const ACCESS_MODES = ['free', 'purchase', 'purchase_or_vip', 'vip', 'unavailable'];

    public function __construct(
        public int $resourceId,
        public string $accessMode,
        public string $rightsStatus,
        public ?int $rightsRecordId,
        public bool $resourceTakenDown,
        public string $requestId,
        public int $actorId,
        public string $nowUtc,
    ) {
        if ($resourceId <= 0 || $actorId <= 0) {
            throw new RightsException('invalid_publication_request', 'Resource and actor IDs must be positive.');
        }
        if ($rightsRecordId !== null && $rightsRecordId < 0) {
            throw new RightsException('invalid_rights_record_id', 'Rights record ID must be non-negative.');
        }
        if (! in_array($accessMode, self::ACCESS_MODES, true)) {
            throw new RightsException('invalid_access_mode', 'Access mode is not supported.');
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $requestId) !== 1) {
            throw new RightsException('invalid_request_id', 'Request ID must be a UUID.');
        }
        if (date_create_immutable($nowUtc) === false) {
            throw new RightsException('invalid_now_utc', 'nowUtc must be an ISO-8601 datetime.');
        }
    }

    public function isPaidAccessMode(): bool
    {
        return in_array($this->accessMode, ['purchase', 'purchase_or_vip', 'vip'], true);
    }
}
