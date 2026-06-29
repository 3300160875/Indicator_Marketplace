<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Outbox;

final class InMemoryOutboxEventStore implements OutboxEventRepositoryInterface
{
    /** @var array<string, OutboxEvent> */
    private array $events = [];

    public function create(OutboxEvent $event): OutboxEvent
    {
        $existing = $this->events[$event->eventKey] ?? null;
        if ($existing !== null) {
            if (
                $existing->eventName === $event->eventName
                && $existing->aggregateType === $event->aggregateType
                && $existing->aggregateId === $event->aggregateId
                && $existing->payload === $event->payload
            ) {
                return $existing;
            }

            throw new OutboxEventException('Outbox event key already exists.');
        }

        $this->events[$event->eventKey] = $event;

        return $event;
    }

    public function save(OutboxEvent $event): OutboxEvent
    {
        $this->events[$event->eventKey] = $event;

        return $event;
    }

    public function findByEventKey(string $eventKey): ?OutboxEvent
    {
        return $this->events[$eventKey] ?? null;
    }

    /**
     * @return list<OutboxEvent>
     */
    public function findDue(string $now, int $batchSize): array
    {
        if ($batchSize <= 0) {
            return [];
        }

        $due = [];
        foreach ($this->events as $event) {
            if (! $event->isDue($now)) {
                continue;
            }

            $due[] = $event;
        }

        usort($due, static function (OutboxEvent $left, OutboxEvent $right): int {
            $leftTs = strtotime($left->availableAt);
            $rightTs = strtotime($right->availableAt);

            if ($leftTs === false || $rightTs === false) {
                return $left->eventKey <=> $right->eventKey;
            }

            if ($leftTs === $rightTs) {
                return $left->eventKey <=> $right->eventKey;
            }

            return $leftTs <=> $rightTs;
        });

        return array_slice($due, 0, $batchSize);
    }
}

