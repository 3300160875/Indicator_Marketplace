<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Plan;

use RuntimeException;

final class MembershipPlanMetaException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }

    public static function missingField(string $field): self
    {
        return new self('missing_field', 'Missing required field '.$field.'.');
    }

    public static function invalidPositiveInt(string $field, int $value): self
    {
        return new self('invalid_positive_int', 'Field '.$field.' must be a positive integer, got '.$value.'.');
    }

    public static function invalidNumeric(string $field): self
    {
        return new self('invalid_numeric', 'Field '.$field.' must be a numeric value.');
    }

    public static function invalidDurationUnit(string $unit): self
    {
        return new self('invalid_duration_unit', 'Unsupported duration unit '.$unit.'.');
    }

    public static function unsupportedLifetime(): self
    {
        return new self('unsupported_lifetime', 'Lifetime plan is not supported at this milestone.');
    }

    public static function invalidScopeType(string $type): self
    {
        return new self('invalid_scope_type', 'Unsupported scope type '.$type.'.');
    }

    public static function invalidScopeRules(string $field): self
    {
        return new self('invalid_scope_rules_json', $field.' must be a valid JSON object/array.');
    }

    public static function invalidExcludedResourceIds(): self
    {
        return new self('invalid_excluded_resource_ids', 'excluded_resource_ids must be a JSON array of IDs.');
    }

    public static function invalidQuotaPeriod(string $period): self
    {
        return new self('invalid_quota_period', 'Unsupported quota period '.$period.'.');
    }

    public static function ruleVersionMissing(): self
    {
        return new self('missing_rules_version', 'rules_version is required.');
    }

    public static function cannotSell(): self
    {
        return new self('plan_not_for_sale', 'Plan is not active for sale.');
    }

    public static function invalidProductType(string $type): self
    {
        return new self('invalid_product_type', 'Invalid product type '.$type.' for membership plan metadata.');
    }

    public static function invalidRedownloadPolicy(string $policy): self
    {
        return new self('invalid_redownload_policy', 'Unsupported redownload policy '.$policy.'.');
    }
}
