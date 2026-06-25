<?php
declare(strict_types=1);

namespace StockResource\Core\Support\Logging;

final readonly class SensitiveFieldRedactor
{
    private const SENSITIVE_PATTERNS = [
        'authorization',
        'cookie',
        'password',
        'proof',
        'secret',
        'storage_key',
        'token',
    ];

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function redact(array $context): array
    {
        $redacted = [];
        foreach ($context as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);
        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
