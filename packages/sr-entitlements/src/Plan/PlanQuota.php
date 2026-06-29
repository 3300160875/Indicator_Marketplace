<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Plan;

enum MembershipQuotaPeriod: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Total = 'total';
}

enum MembershipRedownloadPolicy: string
{
    case CountEach = 'count_each';
    case SameResourceOncePerPeriod = 'same_resource_once_per_period';
}

final readonly class PlanQuota
{
    public function __construct(
        public MembershipQuotaPeriod $period,
        public int $limit,
        public MembershipRedownloadPolicy $redownloadPolicy,
    ) {
        $this->assertInvariant();
    }

    public static function fromMeta(mixed $rawPeriod, mixed $rawLimit, mixed $rawPolicy): self
    {
        $period = self::parsePeriod((string) $rawPeriod);
        $limit = self::parseLimit($rawLimit);
        $policy = self::parsePolicy((string) $rawPolicy);

        return new self(period: $period, limit: $limit, redownloadPolicy: $policy);
    }

    public static function parsePeriod(string $rawPeriod): MembershipQuotaPeriod
    {
        $period = trim(strtolower($rawPeriod));
        $enum = MembershipQuotaPeriod::tryFrom($period);
        if ($enum === null) {
            throw MembershipPlanMetaException::invalidQuotaPeriod($period);
        }

        return $enum;
    }

    public static function parsePolicy(string $rawPolicy): MembershipRedownloadPolicy
    {
        $policy = trim(strtolower($rawPolicy));
        $enum = MembershipRedownloadPolicy::tryFrom($policy);
        if ($enum === null) {
            throw MembershipPlanMetaException::invalidRedownloadPolicy($policy);
        }

        return $enum;
    }

    private static function parseLimit(mixed $rawLimit): int
    {
        if (! is_numeric($rawLimit)) {
            throw MembershipPlanMetaException::invalidNumeric('_sr_quota_limit');
        }

        $limit = (int) $rawLimit;
        if ($limit <= 0) {
            throw MembershipPlanMetaException::invalidPositiveInt('_sr_quota_limit', $limit);
        }

        return $limit;
    }

    private function assertInvariant(): void
    {
        if ($this->limit <= 0) {
            throw MembershipPlanMetaException::invalidPositiveInt('_sr_quota_limit', $this->limit);
        }
    }
}
