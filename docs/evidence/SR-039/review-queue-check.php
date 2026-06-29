<?php

declare(strict_types=1);

use StockResource\PaymentGateways\Admin\ReviewQueue\ReviewQueueException;
use StockResource\PaymentGateways\Admin\ReviewQueue\ReviewQueueService;
use StockResource\PaymentGateways\Submission\InMemoryPaymentSubmissionRepository;
use StockResource\PaymentGateways\Submission\PaymentSubmission;
use StockResource\PaymentGateways\Submission\PaymentSubmissionException;
use StockResource\PaymentGateways\Submission\SubmissionState;
use StockResource\PaymentGateways\Submission\PaymentSubmissionStateMachine;

$root = dirname(__DIR__, 3);
$package = $root.'/packages/sr-payment-gateways';
foreach ([
    '/src/Submission/PaymentSubmission.php',
    '/src/Submission/SubmissionState.php',
    '/src/Submission/PaymentSubmissionException.php',
    '/src/Submission/PaymentSubmissionRepository.php',
    '/src/Submission/InMemoryPaymentSubmissionRepository.php',
    '/src/Submission/PaymentSubmissionStateMachine.php',
    '/src/Admin/ReviewQueue/ReviewQueueService.php',
] as $sourceFile) {
    require_once $package.$sourceFile;
}

function sr039_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr039_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr039_expect_error(string $codeName, callable $callback): void
{
    try {
        $callback();
    } catch (ReviewQueueException | PaymentSubmissionException | RuntimeException $exception) {
        $actualCode = $exception instanceof ReviewQueueException || $exception instanceof PaymentSubmissionException
            ? $exception->codeName
            : 'runtime';

        if ($actualCode === $codeName) {
            return;
        }

        throw new RuntimeException('Expected exception '.$codeName.' but got '.$actualCode.'.');
    }

    throw new RuntimeException('Expected exception '.$codeName);
}

$now = '2026-06-29 10:00:00';

$submission = PaymentSubmission::create(
    submissionKey: '11111111-1111-4111-8111-111111111111',
    orderId: 5001,
    userId: 9001,
    channel: 'alipay',
    expectedAmount: '59.00',
    reportedAmount: '59.00',
    reportedPaidAt: $now,
    proofStorageKey: 'proof/5001/9001/1111.bin',
    proofSha256: str_repeat('a', 64),
    proofMimeType: 'image/png',
    proofFileSize: 2048,
    nowUtc: $now,
    accountKey: 'acct-A',
);

$repository = new InMemoryPaymentSubmissionRepository;
$saved = $repository->create($submission);
$stateMachine = new PaymentSubmissionStateMachine;

// 不允许释放未领取的记录（state must be under_review）。
$service = new ReviewQueueService(
    repository: $repository,
    stateMachine: $stateMachine,
    lockTtlMinutes: 60,
    canReview: static fn (int $reviewerId, PaymentSubmission $candidate, int $nowTimestamp): bool => in_array($reviewerId, [701, 702], true),
    nowProvider: fn (): string => '2026-06-29 10:00:00',
);

// 提交状态可被领取，并标记 reviewer 与 claim 时间。
$claimed = $service->claim(
    submissionId: $saved->id,
    reviewerId: 701,
    expectedLockVersion: 0,
);
sr039_same(SubmissionState::UnderReview, $claimed->state, 'submitted becomes under_review');
sr039_same(1, $claimed->lockVersion, 'claim bumps lock version');
sr039_same(701, $claimed->reviewerId, 'claimed by reviewer 701');
sr039_same($now, $claimed->claimedAt !== null ? $claimed->claimedAt : '', 'claimed_at is recorded');

// 同一 reviewer 在未超时情况下可按同版本重入，返回持有者本身。
$service = new ReviewQueueService(
    repository: $repository,
    stateMachine: $stateMachine,
    lockTtlMinutes: 60,
    canReview: static fn (int $reviewerId, PaymentSubmission $candidate, int $nowTimestamp): bool => $reviewerId !== 999,
    nowProvider: fn (): string => '2026-06-29 10:10:00',
);
$same = $service->claim($saved->id, 701, 1);
sr039_same(701, $same->reviewerId, 'same reviewer can re-claim while lock valid');
sr039_same(1, $same->lockVersion, 'reclaim by same reviewer does not bump lock');

// 锁版本不匹配时拒绝重复领取。
sr039_expect_error('lock_version_mismatch', function () use ($service, $saved): void {
    $service->claim($saved->id, 701, 0);
});

// 其他 reviewer 在锁未过期时不能抢占。
sr039_expect_error('state_conflict', function () use ($service, $saved): void {
    $service->claim($saved->id, 702, 1);
});

// 无财务能力时不能领取。
sr039_expect_error('permission_denied', function () use ($repository, $stateMachine): void {
    $service = new ReviewQueueService(
        repository: $repository,
        stateMachine: $stateMachine,
        lockTtlMinutes: 60,
        canReview: static fn (int $reviewerId, PaymentSubmission $candidate, int $nowTimestamp): bool => false,
        nowProvider: fn (): string => '2026-06-29 10:00:00',
    );

    $service->claim(1, 703, 2);
});

// 超时后可被其他 reviewer 接管：锁版本再次递增。
$service = new ReviewQueueService(
    repository: $repository,
    stateMachine: $stateMachine,
    lockTtlMinutes: 60,
    canReview: static fn (int $reviewerId, PaymentSubmission $candidate, int $nowTimestamp): bool => true,
    nowProvider: fn (): string => '2026-06-29 11:10:00',
);
$reclaimed = $service->claim($saved->id, 702, 1);
sr039_same(702, $reclaimed->reviewerId, 'timed-out claim can be reclaimed by another reviewer');
sr039_same(2, $reclaimed->lockVersion, 'reclaim bump lock version');

// 超时任务可释放，reviewer 与 claimed_at 清空。
$service = new ReviewQueueService(
    repository: $repository,
    stateMachine: $stateMachine,
    lockTtlMinutes: 60,
    canReview: static fn (int $reviewerId, PaymentSubmission $candidate, int $nowTimestamp): bool => true,
    nowProvider: fn (): string => '2026-06-29 12:20:00',
);
$released = $service->releaseTimedOutClaim($saved->id, '2026-06-29 12:20:00');
sr039_same(null, $released->reviewerId, 'release clears reviewer id');
sr039_same(null, $released->claimedAt, 'release clears claimed_at');
sr039_same(3, $released->lockVersion, 'release bumps lock version');

// 不允许释放未领取的记录。
$fresh = $repository->create(PaymentSubmission::create(
    submissionKey: '22222222-2222-4222-8222-222222222222',
    orderId: 5002,
    userId: 9002,
    channel: 'wechat',
    expectedAmount: '10.00',
    reportedAmount: '10.00',
    reportedPaidAt: $now,
    proofStorageKey: 'proof/5002/9002/2222.bin',
    proofSha256: str_repeat('b', 64),
    proofMimeType: 'image/png',
    proofFileSize: 1024,
    nowUtc: $now,
));
$service = new ReviewQueueService(
    repository: $repository,
    stateMachine: $stateMachine,
    lockTtlMinutes: 60,
    canReview: static fn (int $reviewerId, PaymentSubmission $candidate, int $nowTimestamp): bool => true,
    nowProvider: fn (): string => '2026-06-29 10:10:00',
);
sr039_expect_error('invalid_state', fn () => $service->releaseTimedOutClaim($fresh->id, '2026-06-29 11:00:00'));

// 已过期检查：在未超时前，不允许释放。
$waiting = $service->claim($fresh->id, 701, $fresh->lockVersion);
$service = new ReviewQueueService(
    repository: $repository,
    stateMachine: $stateMachine,
    lockTtlMinutes: 60,
    canReview: static fn (int $reviewerId, PaymentSubmission $candidate, int $nowTimestamp): bool => true,
    nowProvider: fn (): string => '2026-06-29 10:20:00',
);
sr039_expect_error('not_expired', fn () => $service->releaseTimedOutClaim($waiting->id, '2026-06-29 10:20:00'));

$needsMore = $repository->update($stateMachine->transition($waiting, 'need_more_info', 1), 1);

// 只有提交中/审核中的提交才可进行领取。
sr039_expect_error('invalid_state', fn () => $service->claim($needsMore->id, 701, $needsMore->lockVersion));

echo "SR-039 review queue checks passed.\n";
