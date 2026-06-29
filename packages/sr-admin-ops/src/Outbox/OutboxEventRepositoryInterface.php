<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Outbox;

interface OutboxEventRepositoryInterface
{
    public function create(OutboxEvent $event): OutboxEvent;

    public function save(OutboxEvent $event): OutboxEvent;

    public function findByEventKey(string $eventKey): ?OutboxEvent;

    /**
     * @return list<OutboxEvent>
     */
    public function findDue(string $now, int $batchSize): array;
}

