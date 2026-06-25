<?php
declare(strict_types=1);

namespace StockResource\Platform\Feature;

final readonly class FeatureFlags
{
    private const DEFAULTS = [
        'SR_PAYMENTS_ENABLED' => false,
        'SR_MANUAL_PAYMENT_ENABLED' => false,
        'SR_UPLOAD_PROOFS_ENABLED' => false,
        'SR_PAID_DOWNLOADS_ENABLED' => false,
        'SR_PRIVATE_DOWNLOADS_ENABLED' => true,
        'SR_DOWNLOAD_TOKEN_ISSUE_ENABLED' => true,
        'SR_STRICT_RIGHTS_GATE' => true,
        'SR_CONTENT_RESTRICTION_ENABLED' => false,
        'SR_OUTBOX_WORKER_ENABLED' => true,
    ];

    /**
     * @param array<string, bool> $flags
     */
    private function __construct(private array $flags)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function fromOptions(array $options): self
    {
        $flags = self::DEFAULTS;

        foreach (array_keys(self::DEFAULTS) as $flag) {
            if (array_key_exists($flag, $options)) {
                $flags[$flag] = filter_var($options[$flag], FILTER_VALIDATE_BOOL);
            }
        }

        return new self($flags);
    }

    public function enabled(string $flag): bool
    {
        return $this->flags[$flag] ?? false;
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        return $this->flags;
    }
}
