<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Plan;

enum MembershipPlanScopeType: string
{
    case All = 'all';
    case Taxonomies = 'taxonomies';
    case Resources = 'resources';
}

final readonly class PlanScope
{
    /**
     * @param array<string, mixed> $rules
     * @param list<int> $excludedResourceIds
     */
    public function __construct(
        public MembershipPlanScopeType $type,
        public array $rules,
        public array $excludedResourceIds,
    ) {
        $this->assertInvariant();
    }

    public static function fromMeta(mixed $rawType, mixed $rawRules, mixed $rawExcluded): self
    {
        $type = self::parseType((string) $rawType);
        $rules = self::parseRules($rawRules);
        $excludedResourceIds = self::parseExcludedResourceIds($rawExcluded);

        return new self(type: $type, rules: $rules, excludedResourceIds: $excludedResourceIds);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scope_type' => $this->type->value,
            'scope_rules_json' => $this->rules,
            'excluded_resource_ids' => $this->excludedResourceIds,
        ];
    }

    public static function parseRules(mixed $value): array
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_values($value);
            }

            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        if (! str_starts_with(trim($value), '{') && ! str_starts_with(trim($value), '[')) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw MembershipPlanMetaException::invalidScopeRules('_sr_scope_rules_json');
        }

        return is_array($decoded) ? $decoded : [];
    }

    private static function parseType(string $rawType): MembershipPlanScopeType
    {
        $type = trim(strtolower($rawType));
        $enum = MembershipPlanScopeType::tryFrom($type);

        if ($enum === null) {
            throw MembershipPlanMetaException::invalidScopeType($type);
        }

        return $enum;
    }

    /**
     * @return list<int>
     */
    private static function parseExcludedResourceIds(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if ($decoded === null && ! is_array($raw)) {
            throw MembershipPlanMetaException::invalidExcludedResourceIds();
        }

        if (! is_array($decoded)) {
            throw MembershipPlanMetaException::invalidExcludedResourceIds();
        }

        $ids = [];
        foreach ($decoded as $item) {
            if (! is_numeric($item)) {
                throw MembershipPlanMetaException::invalidExcludedResourceIds();
            }

            $id = (int) $item;
            if ($id <= 0) {
                continue;
            }

            $ids[(string) $id] = $id;
        }

        ksort($ids);

        return array_values($ids);
    }

    private function assertInvariant(): void
    {
        if ($this->type !== MembershipPlanScopeType::All && $this->rules === []) {
            throw MembershipPlanMetaException::invalidScopeRules('_sr_scope_rules_json');
        }
    }
}
