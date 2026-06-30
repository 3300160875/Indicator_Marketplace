<?php
declare(strict_types=1);

namespace StockResource\Entitlements\ContentRestriction;

use StockResource\Contracts\Entitlement\AccessDecision;

final readonly class RestrictedContentResult
{
    /**
     * @param list<string> $cacheVary
     */
    public function __construct(
        public bool $visible,
        public string $html,
        public AccessDecision $decision,
        public string $reasonCode,
        public array $cacheVary,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'visible' => $this->visible,
            'html' => $this->html,
            'reason_code' => $this->reasonCode,
            'decision' => $this->decision->toArray(),
            'cache_vary' => $this->cacheVary,
        ];
    }
}
