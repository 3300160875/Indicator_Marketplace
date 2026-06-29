<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Outbox;

final readonly class OutboxEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $eventKey,
        public string $eventName,
        public string $aggregateType,
        public string $aggregateId,
        public array $payload,
        public OutboxDeliveryStatus $status = OutboxDeliveryStatus::Pending,
        public int $attempts = 0,
        public string $availableAt = '',
        public ?string $processedAt = null,
        public ?string $lastError = null,
        public string $createdAt = '',
        public string $updatedAt = '',
    ) {
    }

    public static function create(
        string $eventKey,
        string $eventName,
        string $aggregateType,
        string $aggregateId,
        array $payload,
        string $now,
    ): self {
        $eventKey = trim($eventKey);
        if ($eventKey === '') {
            throw new OutboxEventException('event_key is required.');
        }

        $eventName = trim($eventName);
        if ($eventName === '') {
            throw new OutboxEventException('event_name is required.');
        }

        $aggregateType = trim($aggregateType);
        if ($aggregateType === '') {
            throw new OutboxEventException('aggregate_type is required.');
        }

        $aggregateId = trim($aggregateId);
        if ($aggregateId === '') {
            throw new OutboxEventException('aggregate_id is required.');
        }

        if ($now === '') {
            throw new OutboxEventException('timestamp is required.');
        }

        self::assertDateTime('timestamp', $now);

        return new self(
            eventKey: $eventKey,
            eventName: $eventName,
            aggregateType: $aggregateType,
            aggregateId: $aggregateId,
            payload: $payload,
            status: OutboxDeliveryStatus::Pending,
            attempts: 0,
            availableAt: $now,
            processedAt: null,
            lastError: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function withProcessing(string $now): self
    {
        return new self(
            eventKey: $this->eventKey,
            eventName: $this->eventName,
            aggregateType: $this->aggregateType,
            aggregateId: $this->aggregateId,
            payload: $this->payload,
            status: OutboxDeliveryStatus::Processing,
            attempts: $this->attempts + 1,
            availableAt: $this->availableAt,
            processedAt: $this->processedAt,
            lastError: null,
            createdAt: $this->createdAt,
            updatedAt: $now,
        );
    }

    public function withSuccess(string $now): self
    {
        self::assertDateTime('timestamp', $now);

        return new self(
            eventKey: $this->eventKey,
            eventName: $this->eventName,
            aggregateType: $this->aggregateType,
            aggregateId: $this->aggregateId,
            payload: $this->payload,
            status: OutboxDeliveryStatus::Sent,
            attempts: $this->attempts,
            availableAt: $this->availableAt,
            processedAt: $now,
            lastError: null,
            createdAt: $this->createdAt,
            updatedAt: $now,
        );
    }

    public function withFailure(string $now, string $error, int $maxAttempts, int $delaySeconds): self
    {
        self::assertDateTime('timestamp', $now);

        if ($maxAttempts <= 0) {
            throw new OutboxEventException('maxAttempts must be positive.');
        }

        if ($delaySeconds < 0) {
            throw new OutboxEventException('delaySeconds must be non-negative.');
        }

        $nextAttempts = max(1, $this->attempts);
        $nextStatus = OutboxDeliveryStatus::Failed;

        if ($nextAttempts >= $maxAttempts) {
            $nextStatus = OutboxDeliveryStatus::Dead;
        }

        $backoffSeconds = $delaySeconds * (2 ** max($nextAttempts - 1, 0));
        $nextAvailableAt = $this->computeNextAvailableAt($now, $nextStatus === OutboxDeliveryStatus::Failed ? $backoffSeconds : 0);

        return new self(
            eventKey: $this->eventKey,
            eventName: $this->eventName,
            aggregateType: $this->aggregateType,
            aggregateId: $this->aggregateId,
            payload: $this->payload,
            status: $nextStatus,
            attempts: $nextAttempts,
            availableAt: $nextAvailableAt,
            processedAt: $this->processedAt,
            lastError: $error,
            createdAt: $this->createdAt,
            updatedAt: $now,
        );
    }

    private static function computeNextAvailableAt(string $now, int $addSeconds): string
    {
        if ($addSeconds <= 0) {
            return $now;
        }

        $parsed = strtotime($now);
        if ($parsed === false) {
            throw new OutboxEventException('invalid available timestamp.');
        }

        return gmdate('Y-m-d H:i:s', $parsed + $addSeconds);
    }

    public function isDue(string $at): bool
    {
        $eventTs = strtotime($this->availableAt);
        $nowTs = strtotime($at);
        if ($eventTs === false || $nowTs === false) {
            return false;
        }

        return $eventTs <= $nowTs
            && in_array($this->status, [OutboxDeliveryStatus::Pending, OutboxDeliveryStatus::Failed], true);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $payload = $data['payload_json'] ?? [];
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $payload = $decoded;
            } else {
                $payload = [];
            }
        }

        return new self(
            eventKey: trim((string) ($data['event_key'] ?? '')),
            eventName: trim((string) ($data['event_name'] ?? '')),
            aggregateType: trim((string) ($data['aggregate_type'] ?? '')),
            aggregateId: trim((string) ($data['aggregate_id'] ?? '')),
            payload: is_array($payload) ? $payload : [],
            status: OutboxDeliveryStatus::from(trim((string) ($data['status'] ?? OutboxDeliveryStatus::Pending->value))),
            attempts: max(0, (int) ($data['attempts'] ?? 0)),
            availableAt: trim((string) ($data['available_at'] ?? '')),
            processedAt: self::nullableString($data['processed_at'] ?? null),
            lastError: self::nullableString($data['last_error'] ?? null),
            createdAt: trim((string) ($data['created_at'] ?? '')),
            updatedAt: trim((string) ($data['updated_at'] ?? '')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_key' => $this->eventKey,
            'event_name' => $this->eventName,
            'aggregate_type' => $this->aggregateType,
            'aggregate_id' => $this->aggregateId,
            'payload_json' => $this->payload,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'available_at' => $this->availableAt,
            'processed_at' => $this->processedAt,
            'last_error' => $this->lastError,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private static function assertDateTime(string $name, string $value): void
    {
        if ($value === '') {
            throw new OutboxEventException($name.' is required.');
        }

        if (strtotime($value) === false) {
            throw new OutboxEventException($name.' must be parseable datetime.');
        }
    }
}
