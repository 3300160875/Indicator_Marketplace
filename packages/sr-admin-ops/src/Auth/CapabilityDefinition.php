<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Auth;

final readonly class CapabilityDefinition
{
    public function __construct(
        public string $slug,
        public string $label,
        public bool $highRisk = false,
        public bool $ownerRestricted = false,
    ) {}
}
