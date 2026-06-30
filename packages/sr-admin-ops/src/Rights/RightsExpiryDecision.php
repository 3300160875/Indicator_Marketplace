<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Rights;

final readonly class RightsExpiryDecision
{
    public function __construct(
        public bool $warningRequired,
        public bool $pauseRequired,
        public ?int $daysUntilExpiry,
        public string $reasonCode,
    ) {
    }
}
