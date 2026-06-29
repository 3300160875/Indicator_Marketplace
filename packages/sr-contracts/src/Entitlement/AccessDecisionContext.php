<?php
declare(strict_types=1);

namespace StockResource\Contracts\Entitlement;

use InvalidArgumentException;

final readonly class AccessDecisionContext
{
    private const ACCESS_MODES = ['free', 'purchase', 'vip', 'purchase_or_vip', 'unavailable'];

    /**
     * @param list<int> $taxonomyTermIds
     */
    public function __construct(
        public int $resourceId,
        public ?int $userId,
        public string $accessMode,
        public string $resourceStatus,
        public array $taxonomyTermIds,
        public string $atUtc,
    ) {
        if ($this->resourceId < 1) {
            throw new InvalidArgumentException('resource_id must be a positive integer.');
        }

        if ($this->userId !== null && $this->userId < 1) {
            throw new InvalidArgumentException('user_id must be null or a positive integer.');
        }

        if (! in_array($this->accessMode, self::ACCESS_MODES, true)) {
            throw new InvalidArgumentException('access_mode is not supported.');
        }

        if (trim($this->resourceStatus) === '') {
            throw new InvalidArgumentException('resource_status is required.');
        }

        foreach ($this->taxonomyTermIds as $termId) {
            if (! is_int($termId) || $termId < 1) {
                throw new InvalidArgumentException('taxonomy_term_ids must contain positive integers.');
            }
        }

        if (strtotime($this->atUtc) === false) {
            throw new InvalidArgumentException('at_utc must be a valid datetime string.');
        }
    }
}
