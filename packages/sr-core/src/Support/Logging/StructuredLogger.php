<?php
declare(strict_types=1);

namespace StockResource\Core\Support\Logging;

final readonly class StructuredLogger
{
    public function __construct(
        private InMemoryLogSink $sink,
        private SensitiveFieldRedactor $redactor,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $event, string $message, array $context = []): void
    {
        $this->log('info', $event, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $event, string $message, array $context = []): void
    {
        $this->log('error', $event, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $event, string $message, array $context): void
    {
        $this->sink->write($level, $event, $message, $this->redactor->redact($context));
    }
}
