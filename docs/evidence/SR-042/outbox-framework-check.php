<?php

declare(strict_types=1);

use StockResource\AdminOps\Outbox\InMemoryOutboxEventStore;
use StockResource\AdminOps\Outbox\OutboxDeliveryStatus;
use StockResource\AdminOps\Outbox\OutboxEvent;
use StockResource\AdminOps\Outbox\OutboxEventSender;
use StockResource\AdminOps\Outbox\OutboxWorker;
use StockResource\AdminOps\Outbox\PaymentReviewOutbox;

$root = dirname(__DIR__, 3);
$package = $root . '/packages/sr-admin-ops';
foreach ([
    '/src/Outbox/OutboxDeliveryStatus.php',
    '/src/Outbox/OutboxEventException.php',
    '/src/Outbox/OutboxEvent.php',
    '/src/Outbox/OutboxEventRepositoryInterface.php',
    '/src/Outbox/InMemoryOutboxEventStore.php',
    '/src/Outbox/OutboxEventSender.php',
    '/src/Outbox/OutboxDeliveryResult.php',
    '/src/Outbox/OutboxWorker.php',
    '/src/Outbox/PaymentReviewOutbox.php',
] as $file) {
    require_once $package . $file;
}

function sr042_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr042_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr042_event_key(int $submissionId, int $orderId): string
{
    return PaymentReviewOutbox::defaultEventKey($submissionId, $orderId);
}

/**
 * @param string[] $calls
 */
final class TrackingSender implements OutboxEventSender
{
    /**
     * @param array<string> $calls
     */
    public function __construct(
        private array &$calls,
        private int $maxFailures,
        private int &$failedCount = 0,
    ) {
    }

    public function send(OutboxEvent $event): void
    {
        $this->calls[] = $event->eventKey;
        if ($this->failedCount < $this->maxFailures) {
            $this->failedCount++;

            throw new RuntimeException('send failed #' . $this->failedCount);
        }
    }
}

// 1) 基础事件创建与 payload 契约。
$createdAt = '2026-06-29 10:00:00';
$event = PaymentReviewOutbox::reviewedEvent(
    submissionId: 101,
    orderId: 2001,
    userId: 7001,
    state: 'approved',
    reviewerId: 9001,
    eventKey: sr042_event_key(101, 2001),
    now: $createdAt,
);

sr042_same(PaymentReviewOutbox::EVENT_NAME, $event->eventName, 'event name is fixed to payment reviewed');
sr042_same(PaymentReviewOutbox::AGGREGATE_TYPE, $event->aggregateType, 'aggregate type fixed to payment submission');
sr042_same(OutboxDeliveryStatus::Pending, $event->status, 'new outbox event is pending');
sr042_same(0, $event->attempts, 'new outbox event has no attempts');

// 2) Outbox 仓储幂等写入（相同事件键返回旧值）。
$store = new InMemoryOutboxEventStore;
$store->create($event);

$same = PaymentReviewOutbox::reviewedEvent(
    submissionId: 101,
    orderId: 2001,
    userId: 7001,
    state: 'approved',
    reviewerId: 9001,
    eventKey: sr042_event_key(101, 2001),
    now: $createdAt,
);

$stored = $store->create($same);
sr042_same($event->eventKey, $stored->eventKey, 'duplicated event key returns existing record');

// 3) 事件发送成功路径可直接落库 Sent。
$successCalls = [];
$successSender = new TrackingSender($successCalls, maxFailures: 0);
$successStore = new InMemoryOutboxEventStore;
$successStore->create($event);

$successWorker = new OutboxWorker(
    repository: $successStore,
    sender: $successSender,
    nowProvider: static fn (): string => '2026-06-29 10:00:00',
    maxAttempts: 3,
    baseDelaySeconds: 10,
    batchSize: 10,
);

$successResult = $successWorker->run();
sr042_same(1, $successResult->processed, 'one event processed in success run');
sr042_same(1, $successResult->succeeded, 'one event succeeded in success run');
sr042_same(0, $successResult->retried, 'no retry when success');
sr042_same(0, $successResult->dead, 'no dead messages when success');

$sent = $successStore->findByEventKey($event->eventKey);
sr042_assert($sent !== null, 'sent event should exist');
sr042_same(OutboxDeliveryStatus::Sent, $sent->status, 'successful send marks event sent');
sr042_same(1, $sent->attempts, 'first send consumes one processing attempt');

// 4) 发送失败重试与死信。
$failCalls = [];
$failSender = new TrackingSender($failCalls, maxFailures: 2);
$retryStore = new InMemoryOutboxEventStore;
$retryEvent = PaymentReviewOutbox::reviewedEvent(
    submissionId: 102,
    orderId: 2002,
    userId: 7002,
    state: 'approved',
    reviewerId: 9002,
    eventKey: sr042_event_key(102, 2002),
    now: '2026-06-29 10:10:00',
);
$retryStore->create($retryEvent);

$workerClock = ['2026-06-29 10:10:00', '2026-06-29 10:10:05', '2026-06-29 10:10:25'];
$retryWorker = new OutboxWorker(
    repository: $retryStore,
    sender: $failSender,
    nowProvider: static function () use (&$workerClock): string {
        $now = array_shift($workerClock);
        if ($now === null) {
            return '2026-06-29 10:10:25';
        }

        return $now;
    },
    maxAttempts: 2,
    baseDelaySeconds: 10,
    batchSize: 10,
);

$firstRetry = $retryWorker->run();
sr042_same(1, $firstRetry->processed, 'first retry-run processes one event');
sr042_same(0, $firstRetry->succeeded, 'first retry-run fails this message');
sr042_same(1, $firstRetry->retried, 'failed message is scheduled for retry');

$firstState = $retryStore->findByEventKey($retryEvent->eventKey);
sr042_assert($firstState !== null, 'retry event should still exist');
sr042_same(OutboxDeliveryStatus::Failed, $firstState->status, 'first failure moves event to failed status');
sr042_same(1, $firstState->attempts, 'first failure records one attempt');
sr042_same('2026-06-29 10:10:10', $firstState->availableAt, 'backoff should be computed at 10 seconds for first retry');

$secondRetry = $retryWorker->run();
sr042_same(0, $secondRetry->processed, 'event not due before retry time');

$thirdRetry = $retryWorker->run();
sr042_same(1, $thirdRetry->processed, 'event becomes due and processes on second retry window');
sr042_same(0, $thirdRetry->succeeded, 'second failure turns message dead');
sr042_same(0, $thirdRetry->retried, 'second failure directly dead with maxAttempts=2');
sr042_same(1, $thirdRetry->dead, 'second failure is dead-lettered');

$finalState = $retryStore->findByEventKey($retryEvent->eventKey);
sr042_assert($finalState !== null, 'final retry state must exist');
sr042_same(OutboxDeliveryStatus::Dead, $finalState->status, 'dead-letter status after max attempts reached');
sr042_same(2, $finalState->attempts, 'max attempts consumed as 2');

// 5) 部分失败不阻塞其他事件处理。
$mixedCalls = [];
$mixedSender = new class($mixedCalls, 1) implements OutboxEventSender {
    /** @param array<string> &$calls */
    public function __construct(
        private array &$calls,
        private int $failTimes,
        private int $callsTotal = 0,
    ) {
    }

    public function send(OutboxEvent $event): void
    {
        $this->calls[] = $event->eventKey;
        if ($this->callsTotal < $this->failTimes) {
            $this->callsTotal++;

            throw new RuntimeException('flaky delivery fail');
        }
    }
};

$mixedStore = new InMemoryOutboxEventStore;
$mixedFailEvent = PaymentReviewOutbox::reviewedEvent(
    submissionId: 103,
    orderId: 2003,
    userId: 7003,
    state: 'approved',
    reviewerId: 9003,
    eventKey: sr042_event_key(103, 2003),
    now: '2026-06-29 10:20:00',
);
$mixedSuccessEvent = PaymentReviewOutbox::reviewedEvent(
    submissionId: 104,
    orderId: 2004,
    userId: 7004,
    state: 'approved',
    reviewerId: 9004,
    eventKey: sr042_event_key(104, 2004),
    now: '2026-06-29 10:20:00',
);
$mixedStore->create($mixedFailEvent);
$mixedStore->create($mixedSuccessEvent);

$mixedWorker = new OutboxWorker(
    repository: $mixedStore,
    sender: $mixedSender,
    nowProvider: static fn (): string => '2026-06-29 10:20:00',
    maxAttempts: 3,
    baseDelaySeconds: 10,
    batchSize: 10,
);

$mixedResult = $mixedWorker->run();
sr042_same(2, $mixedResult->processed, 'mixed run handles both due events');
sr042_same(1, $mixedResult->succeeded, 'mixed run still succeeds for unaffected events');
sr042_same(1, $mixedResult->retried, 'mixed run should mark failing event for retry');

echo "SR-042 outbox framework checks passed.\n";
