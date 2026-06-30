<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Audit;

final readonly class AuditRedactor
{
    /** @param list<string> $sensitiveFragments */
    public function __construct(
        private array $sensitiveFragments = [
            'authorization',
            'cookie',
            'secret',
            'password',
            'token',
            'proof',
            'storage_key',
            'download_url',
            'signature',
        ],
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function redact(array $metadata): array
    {
        $redacted = [];
        foreach ($metadata as $key => $value) {
            if ($this->isSensitive((string) $key)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = strtolower($key);
        foreach ($this->sensitiveFragments as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
