<?php
declare(strict_types=1);

namespace StockResource\Core\Support\Logging;

final class InMemoryLogSink
{
    /** @var list<array{level: string, event: string, message: string, context: array<string, mixed>}> */
    private array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function write(string $level, string $event, string $message, array $context): void
    {
        $this->records[] = [
            'level' => $level,
            'event' => $event,
            'message' => $message,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{level: string, event: string, message: string, context: array<string, mixed>}>
     */
    public function records(): array
    {
        return $this->records;
    }
}
