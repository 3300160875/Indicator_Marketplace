<?php
declare(strict_types=1);

namespace StockResource\PrivateDownloads\Security;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class DownloadSecurityPolicy
{
    /**
     * @param list<RateLimitRule> $rateLimitRules
     */
    public function __construct(
        private DownloadSecurityStore $store,
        private DownloadSecurityEventSink $events,
        private array $rateLimitRules,
        private int $sharingWarningDistinctFingerprints,
        private int $sharingRestrictionDistinctFingerprints,
        private int $sharingRestrictionSeconds,
    ) {
        if ($this->sharingWarningDistinctFingerprints < 1 || $this->sharingRestrictionDistinctFingerprints < 1) {
            throw new InvalidArgumentException('sharing thresholds must be positive.');
        }
        if ($this->sharingWarningDistinctFingerprints > $this->sharingRestrictionDistinctFingerprints) {
            throw new InvalidArgumentException('warning threshold must not exceed restriction threshold.');
        }
        if ($this->sharingRestrictionSeconds < 1) {
            throw new InvalidArgumentException('sharing restriction seconds must be positive.');
        }
    }

    public function inspect(DownloadSecurityRequest $request): DownloadSecurityDecision
    {
        $activeRestriction = $this->store->activeRestriction($request->userId, $request->nowUtc);
        if ($activeRestriction !== null) {
            return $this->block($request, 'account_sharing_restricted', retryAfterUtc: $activeRestriction);
        }

        if ($this->store->hasTokenFingerprint($request->tokenFingerprint)) {
            return $this->block($request, 'token_replay');
        }

        foreach ($this->rateLimitRules as $rule) {
            $count = $this->store->countAttempts($rule->bucket($request), $request->nowUtc, $rule->windowSeconds);
            if ($count >= $rule->maxAttempts) {
                return $this->block($request, 'rate_limited_'.$rule->dimension);
            }
        }

        $this->store->recordAttempt($request);
        $this->store->rememberTokenFingerprint($request->tokenFingerprint);
        $fingerprintCount = $this->store->distinctFingerprints($request->userId, $request->nowUtc, 86400);

        if ($fingerprintCount >= $this->sharingRestrictionDistinctFingerprints) {
            $until = self::plusSeconds($request->nowUtc, $this->sharingRestrictionSeconds);
            $this->store->restrictUser($request->userId, $until);

            return $this->block($request, 'account_sharing_restricted', retryAfterUtc: $until);
        }

        if ($fingerprintCount >= $this->sharingWarningDistinctFingerprints) {
            $this->events->record(SecurityEventRecord::warning($request, 'account_sharing_risk'));

            return DownloadSecurityDecision::allow(['account_sharing_risk']);
        }

        return DownloadSecurityDecision::allow();
    }

    private function block(DownloadSecurityRequest $request, string $code, ?string $retryAfterUtc = null): DownloadSecurityDecision
    {
        $this->events->record(SecurityEventRecord::blocked($request, $code, $retryAfterUtc));

        return DownloadSecurityDecision::block($code, $retryAfterUtc);
    }

    private static function plusSeconds(string $nowUtc, int $seconds): string
    {
        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $nowUtc);
        if (! $date instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('now_utc must be ISO-8601.');
        }

        return $date->modify('+'.$seconds.' seconds')->format(DateTimeInterface::ATOM);
    }
}
