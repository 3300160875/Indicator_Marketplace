<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Rights;

final readonly class RightsExpiryPolicy
{
    public function __construct(
        private int $warningLeadDays = 14,
        private string $expiredAction = 'pause_publication',
    ) {
        if ($warningLeadDays < 0 || $warningLeadDays > 365) {
            throw new RightsException('invalid_warning_lead_days', 'Warning lead days must be between 0 and 365.');
        }
        if (! in_array($expiredAction, ['warn_only', 'pause_publication'], true)) {
            throw new RightsException('invalid_expired_action', 'Expired action must be warn_only or pause_publication.');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            warningLeadDays: max(0, (int) ($config['warning_lead_days'] ?? 14)),
            expiredAction: trim((string) ($config['expired_action'] ?? 'pause_publication')),
        );
    }

    public function evaluate(RightsRecord $record, string $nowUtc): RightsExpiryDecision
    {
        if ($record->expiresAt === null) {
            return new RightsExpiryDecision(false, false, null, 'no_expiry');
        }

        $now = self::date($nowUtc);
        $expiresAt = self::date($record->expiresAt);
        $secondsUntilExpiry = $expiresAt->getTimestamp() - $now->getTimestamp();

        if ($secondsUntilExpiry <= 0) {
            return new RightsExpiryDecision(
                warningRequired: true,
                pauseRequired: $this->expiredAction === 'pause_publication',
                daysUntilExpiry: 0,
                reasonCode: 'rights_expired',
            );
        }

        $daysUntilExpiry = (int) ceil($secondsUntilExpiry / 86400);

        return new RightsExpiryDecision(
            warningRequired: $daysUntilExpiry <= $this->warningLeadDays,
            pauseRequired: false,
            daysUntilExpiry: $daysUntilExpiry,
            reasonCode: $daysUntilExpiry <= $this->warningLeadDays ? 'rights_expiring' : 'active',
        );
    }

    private static function date(string $value): \DateTimeImmutable
    {
        $date = date_create_immutable($value);
        if (! $date instanceof \DateTimeImmutable) {
            throw new RightsException('invalid_datetime', 'Datetime must be valid.');
        }

        return $date;
    }
}
