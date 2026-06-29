<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Outbox;

use Throwable;

final readonly class OutboxWorker
{
    private const DEFAULT_BATCH_SIZE = 50;
    private const DEFAULT_MAX_ATTEMPTS = 5;
    private const DEFAULT_DELAY_SECONDS = 30;

    private mixed $nowProvider;

    public function __construct(
        private OutboxEventRepositoryInterface $repository,
        private OutboxEventSender $sender,
        mixed $nowProvider = null,
        private int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        private int $baseDelaySeconds = self::DEFAULT_DELAY_SECONDS,
        private int $batchSize = self::DEFAULT_BATCH_SIZE,
    ) {
        $this->nowProvider = $nowProvider ?? static fn (): string => gmdate('Y-m-d H:i:s');

        if ($this->maxAttempts <= 0) {
            throw new OutboxEventException('maxAttempts must be positive.');
        }

        if ($this->baseDelaySeconds < 0) {
            throw new OutboxEventException('baseDelaySeconds must be non-negative.');
        }

        if ($this->batchSize <= 0) {
            throw new OutboxEventException('batchSize must be positive.');
        }
    }

    public function run(): OutboxDeliveryResult
    {
        $now = $this->now();
        $events = $this->repository->findDue($now, $this->batchSize);

        $processed = 0;
        $succeeded = 0;
        $retried = 0;
        $dead = 0;

        foreach ($events as $event) {
            $processingNow = $now;
            $processed++;
            $inflight = $event->withProcessing($processingNow);
            $this->repository->save($inflight);

            try {
                $this->sender->send($inflight);
                $this->repository->save($inflight->withSuccess($processingNow));
                $succeeded++;

                continue;
            } catch (Throwable $exception) {
                $failed = $inflight->withFailure(
                    $processingNow,
                    $this->sanitizeError((string) $exception->getMessage()),
                    $this->maxAttempts,
                    $this->baseDelaySeconds,
                );
                $this->repository->save($failed);

                if ($failed->status === OutboxDeliveryStatus::Dead) {
                    $dead++;
                } else {
                    $retried++;
                }
            }
        }

        return new OutboxDeliveryResult(
            processed: $processed,
            succeeded: $succeeded,
            retried: $retried,
            dead: $dead,
        );
    }

    private function now(): string
    {
        return (string) ($this->nowProvider)();
    }

    private function sanitizeError(string $message): string
    {
        return trim($message) === '' ? 'Unknown delivery failure.' : $message;
    }
}
