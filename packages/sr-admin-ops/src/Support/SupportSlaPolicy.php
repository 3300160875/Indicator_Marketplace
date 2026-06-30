<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Support;

final readonly class SupportSlaPolicy
{
    public function __construct(
        private int $highResponseMinutes = 60,
        private int $normalResponseMinutes = 240,
        private int $lowResponseMinutes = 480,
        private int $urgentResponseMinutes = 30,
    ) {
    }

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): self
    {
        return new self(
            highResponseMinutes: max(1, (int) ($config['high_response_minutes'] ?? 60)),
            normalResponseMinutes: max(1, (int) ($config['normal_response_minutes'] ?? 240)),
            lowResponseMinutes: max(1, (int) ($config['low_response_minutes'] ?? 480)),
            urgentResponseMinutes: max(1, (int) ($config['urgent_response_minutes'] ?? 30)),
        );
    }

    public function firstResponseDueAt(SupportTicket $ticket): string
    {
        $created = date_create_immutable($ticket->createdAt);
        if (! $created instanceof \DateTimeImmutable) {
            throw new SupportException('invalid_created_at', 'created_at must be valid.');
        }

        return $created->modify('+'.$this->minutesFor($ticket->priority).' minutes')->format(DATE_ATOM);
    }

    public function isBreached(SupportTicket $ticket, string $nowUtc): bool
    {
        if ($ticket->firstResponseAt !== null) {
            return false;
        }

        $now = date_create_immutable($nowUtc);
        $due = date_create_immutable($this->firstResponseDueAt($ticket));

        return $now instanceof \DateTimeImmutable && $due instanceof \DateTimeImmutable && $now > $due;
    }

    private function minutesFor(string $priority): int
    {
        return match ($priority) {
            'urgent' => $this->urgentResponseMinutes,
            'high' => $this->highResponseMinutes,
            'low' => $this->lowResponseMinutes,
            default => $this->normalResponseMinutes,
        };
    }
}
