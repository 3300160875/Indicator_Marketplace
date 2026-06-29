<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Plan;

enum MembershipPlanDurationUnit: string
{
    case Day = 'day';
    case Month = 'month';
    case Year = 'year';
    case Lifetime = 'lifetime';
}

final readonly class PlanDuration
{
    public function __construct(
        public int $value,
        public MembershipPlanDurationUnit $unit,
    ) {
        $this->assertInvariant();
    }

    public static function fromMeta(mixed $rawValue, mixed $rawUnit): self
    {
        $value = self::parsePositiveInt($rawValue, '_sr_duration_value');
        $unit = self::parseUnit((string) $rawUnit);

        return new self(value: $value, unit: $unit);
    }

    private static function parsePositiveInt(mixed $value, string $field): int
    {
        if (! is_numeric($value)) {
            throw MembershipPlanMetaException::invalidNumeric($field);
        }

        $integer = (int) $value;
        if ($integer <= 0) {
            throw MembershipPlanMetaException::invalidPositiveInt($field, $integer);
        }

        return $integer;
    }

    private static function parseUnit(string $unit): MembershipPlanDurationUnit
    {
        $value = trim(strtolower($unit));
        $enum = MembershipPlanDurationUnit::tryFrom($value);
        if ($enum === null) {
            throw MembershipPlanMetaException::invalidDurationUnit($value);
        }

        if ($enum === MembershipPlanDurationUnit::Lifetime) {
            throw MembershipPlanMetaException::unsupportedLifetime();
        }

        return $enum;
    }

    private function assertInvariant(): void
    {
        if ($this->value < 1) {
            throw MembershipPlanMetaException::invalidPositiveInt('_sr_duration_value', $this->value);
        }
    }
}

