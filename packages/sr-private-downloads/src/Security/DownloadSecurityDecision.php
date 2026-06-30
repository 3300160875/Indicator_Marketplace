<?php
declare(strict_types=1);

namespace StockResource\PrivateDownloads\Security;

final readonly class DownloadSecurityDecision
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public bool $allowed,
        public string $code,
        public array $warnings = [],
        public ?string $retryAfterUtc = null,
    ) {
    }

    /**
     * @param list<string> $warnings
     */
    public static function allow(array $warnings = []): self
    {
        return new self(true, 'allowed', $warnings);
    }

    public static function block(string $code, ?string $retryAfterUtc = null): self
    {
        return new self(false, $code, [], $retryAfterUtc);
    }
}
