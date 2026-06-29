<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Plan;

final readonly class MembershipPlanRules
{
    public function __construct(
        public string $planCode,
        public PlanDuration $duration,
        public PlanScope $scope,
        public PlanQuota $quota,
        public string $rulesVersion,
        public bool $planActive,
        public int $priority,
        public ?string $productType,
    ) {
        $this->assertInvariant();
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function fromMeta(array $meta): self
    {
        $planCode = trim((string) ($meta['_sr_plan_code'] ?? ''));
        if ($planCode === '') {
            throw MembershipPlanMetaException::missingField('_sr_plan_code');
        }

        $duration = PlanDuration::fromMeta(
            $meta['_sr_duration_value'] ?? null,
            $meta['_sr_duration_unit'] ?? '',
        );

        $scope = PlanScope::fromMeta(
            $meta['_sr_scope_type'] ?? '',
            $meta['_sr_scope_rules_json'] ?? null,
            $meta['_sr_excluded_resource_ids'] ?? null,
        );

        $quota = PlanQuota::fromMeta(
            $meta['_sr_quota_period'] ?? null,
            $meta['_sr_quota_limit'] ?? null,
            $meta['_sr_redownload_policy'] ?? '',
        );

        $rulesVersion = trim((string) ($meta['_sr_rules_version'] ?? ''));
        if ($rulesVersion === '') {
            throw MembershipPlanMetaException::ruleVersionMissing();
        }

        $planActive = self::parsePlanActive($meta['_sr_plan_active'] ?? false);
        $priority = max(0, (int) ($meta['_sr_priority'] ?? 100));

        $productType = isset($meta['_sr_product_type']) ? trim((string) $meta['_sr_product_type']) : null;
        if ($productType !== null && $productType !== '' && $productType !== 'membership_plan') {
            throw MembershipPlanMetaException::invalidProductType($productType);
        }

        return new self(
            planCode: $planCode,
            duration: $duration,
            scope: $scope,
            quota: $quota,
            rulesVersion: $rulesVersion,
            planActive: $planActive,
            priority: $priority,
            productType: $productType,
        );
    }

    public function isSellable(): bool
    {
        return $this->planActive;
    }

    public function assertSellable(): void
    {
        if (! $this->planActive) {
            throw MembershipPlanMetaException::cannotSell();
        }
    }

    public function toOrderTermsSnapshot(): array
    {
        return [
            'duration' => [
                'value' => $this->duration->value,
                'unit' => $this->duration->unit->value,
            ],
            'scope' => [
                'type' => $this->scope->type->value,
                'rules' => $this->scope->rules,
                'excluded_resource_ids' => $this->scope->excludedResourceIds,
            ],
            'quota' => [
                'period' => $this->quota->period->value,
                'limit' => $this->quota->limit,
                'redownload_policy' => $this->quota->redownloadPolicy->value,
            ],
            'rules_version' => $this->rulesVersion,
        ];
    }

    private function assertInvariant(): void
    {
        if ($this->planCode === '') {
            throw MembershipPlanMetaException::missingField('_sr_plan_code');
        }

        if ($this->rulesVersion === '') {
            throw MembershipPlanMetaException::ruleVersionMissing();
        }
    }

    private static function parsePlanActive(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value > 0;
        }

        if (! is_string($value)) {
            return false;
        }

        $normalized = trim(strtolower($value));
        return match ($normalized) {
            '1', 'true', 'yes', 'on', 'active' => true,
            '0', 'false', 'no', 'off', '' => false,
            default => false,
        };
    }
}
