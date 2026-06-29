<?php

declare(strict_types=1);

use StockResource\PaymentGateways\Application\PaymentReviewException;
use StockResource\PaymentGateways\Application\PaymentReviewService;
use StockResource\PaymentGateways\Gateway\ManualQr\PaymentApprovalService;
use StockResource\PaymentGateways\Gateway\ManualQr\PaymentSubmissionStateMachine as ManualPaymentSubmissionStateMachine;
use StockResource\PaymentGateways\Submission\InMemoryPaymentSubmissionRepository;
use StockResource\PaymentGateways\Submission\PaymentSubmission;
use StockResource\PaymentGateways\Submission\PaymentSubmissionStateMachine;

$root = dirname(__DIR__, 3);
$package = $root.'/packages/sr-payment-gateways';
foreach ([
    '/src/Submission/PaymentSubmission.php',
    '/src/Submission/SubmissionState.php',
    '/src/Submission/PaymentSubmissionException.php',
    '/src/Submission/PaymentSubmissionRepository.php',
    '/src/Submission/PaymentSubmissionStateMachine.php',
    '/src/Submission/InMemoryPaymentSubmissionRepository.php',
    '/src/Gateway/ManualQr/ManualQrException.php',
    '/src/Gateway/ManualQr/PaymentSubmission.php',
    '/src/Gateway/ManualQr/TransactionFingerprint.php',
    '/src/Gateway/ManualQr/PaymentSubmissionStateMachine.php',
    '/src/Gateway/ManualQr/PaymentApprovalService.php',
    '/src/Application/PaymentReviewService.php',
] as $sourceFile) {
    require_once $package.$sourceFile;
}

function sr040_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr040_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr040_expect_error(string $codeName, callable $callback): void
{
    try {
        $callback();
    } catch (PaymentReviewException $exception) {
        if ($exception->codeName === $codeName) {
            return;
        }

        throw new RuntimeException('Expected '.$codeName.' but got '.$exception->codeName.'.');
    }

    throw new RuntimeException('Expected exception '.$codeName);
}

function sr040_make_under_review_submission(
    InMemoryPaymentSubmissionRepository $repository,
    string $submissionKey,
    int $orderId,
    string $expectedAmount,
    string $proofHash,
    ?string $accountKey = null,
): PaymentSubmission {
    $submission = PaymentSubmission::create(
        submissionKey: $submissionKey,
        orderId: $orderId,
        userId: 9000 + $orderId,
        channel: 'alipay',
        expectedAmount: $expectedAmount,
        reportedAmount: $expectedAmount,
        reportedPaidAt: '2026-06-29 10:00:00',
        proofStorageKey: 'proof/'.$orderId.'/'.(9000 + $orderId).'/'.str_repeat('0', 4).'.png',
        proofSha256: $proofHash,
        proofMimeType: 'image/png',
        proofFileSize: 1024,
        nowUtc: '2026-06-29 10:00:00',
        accountKey: $accountKey,
    );

    $stored = $repository->create($submission);
    $machine = new PaymentSubmissionStateMachine;
    $underReview = $machine->transition($stored, 'claim_review', $stored->lockVersion);

    return $repository->update($underReview, $stored->lockVersion);
}

$approvalService = new PaymentApprovalService(new ManualPaymentSubmissionStateMachine);
$completeRecords = [];

$completeOrder = function (int $submissionId, int $orderId, string $expectedAmount, string $verifiedAmount, string $verifiedPaidAt) use (&$completeRecords): bool {
    $completeRecords[] = [
        'submission_id' => $submissionId,
        'order_id' => $orderId,
        'expected_amount' => $expectedAmount,
        'verified_amount' => $verifiedAmount,
        'verified_paid_at' => $verifiedPaidAt,
    ];

    return true;
};

$canApprove = static fn (int $reviewerId, PaymentSubmission $candidate): bool => in_array($reviewerId, [401, 402], true);

$repository = new InMemoryPaymentSubmissionRepository;
$service = new PaymentReviewService(
    repository: $repository,
    approvalService: $approvalService,
    completeOrder: $completeOrder,
    canApprove: $canApprove,
    nowProvider: static fn (): string => '2026-06-29T10:05:00+08:00',
);

// 1) 首次审批成功并触发订单完成。
$submission = sr040_make_under_review_submission($repository, '11111111-1111-4111-8111-111111111111', 601, '59.00', str_repeat('a', 64), 'acct-A');

$approved = $service->approve(
    submissionId: $submission->id,
    reviewerId: 401,
    expectedLockVersion: $submission->lockVersion,
    idempotencyKey: 'approve-001',
    externalReference: 'TXN-001',
    verifiedAmount: '59.00',
    verifiedPaidAt: '2026-06-29T10:30:00+08:00',
    extra: ['decision_code' => 'APPROVED', 'internal_note' => 'test', 'user_message' => 'ok'],
);

sr040_same(false, $approved['idempotent_replay'], 'approve first call is not a replay');
sr040_same(true, $approved['complete_order'], 'approve first call completes order');
sr040_same(2, $approved['submission']->lockVersion, 'approve increments lock version');
sr040_same('APPROVED', $approved['submission']->decisionCode, 'decision code persisted');
sr040_same(hash('sha256', 'approve-001'), $approved['submission']->approvalIdempotencyKeyHash, 'idempotency hash persisted');

sr040_same(601, $completeRecords[array_key_last($completeRecords)]['order_id'], 'complete order receives order id');
sr040_same('59.00', $completeRecords[array_key_last($completeRecords)]['expected_amount'], 'complete order receives expected amount');
sr040_same('59.00', $completeRecords[array_key_last($completeRecords)]['verified_amount'], 'complete order receives verified amount');

// 2) 重放已批准提交。
$replay = $service->approve(
    submissionId: $submission->id,
    reviewerId: 401,
    expectedLockVersion: $approved['submission']->lockVersion,
    idempotencyKey: 'approve-001',
    externalReference: 'TXN-001',
    verifiedAmount: '59.00',
    verifiedPaidAt: '2026-06-29T10:30:00+08:00',
);

sr040_same(true, $replay['idempotent_replay'], 'approved submission returns idempotent replay');
sr040_same(true, $replay['complete_order'], 'replay also tries order completion');
sr040_same($approved['submission']->transactionFingerprint, $replay['submission']->transactionFingerprint, 'replay keeps transaction fingerprint');

// 3) 重放 idempotency key 变化应拒绝。
sr040_expect_error('idempotency_conflict', function () use ($service, $submission): void {
    $service->approve(
        submissionId: $submission->id,
        reviewerId: 401,
        expectedLockVersion: 2,
        idempotencyKey: 'approve-002',
        externalReference: 'TXN-001',
        verifiedAmount: '59.00',
        verifiedPaidAt: '2026-06-29T10:30:00+08:00',
    );
});

// 4) 重放时审核人变更应被拒绝。
sr040_expect_error('permission_conflict', function () use ($service, $submission): void {
    $service->approve(
        submissionId: $submission->id,
        reviewerId: 402,
        expectedLockVersion: 2,
        idempotencyKey: 'approve-001',
        externalReference: 'TXN-001',
        verifiedAmount: '59.00',
        verifiedPaidAt: '2026-06-29T10:30:00+08:00',
    );
});

// 5) 无审批权限。
sr040_expect_error('permission_denied', function () use ($repository, $approvalService): void {
    $noPermService = new PaymentReviewService(
        repository: $repository,
        approvalService: $approvalService,
        completeOrder: static fn (): bool => true,
        canApprove: static fn (int $reviewerId, PaymentSubmission $candidate): bool => false,
        nowProvider: static fn (): string => '2026-06-29T10:08:00+08:00',
    );

    $target = sr040_make_under_review_submission(
        repository: $repository,
        submissionKey: '22222222-2222-4222-8222-222222222222',
        orderId: 602,
        expectedAmount: '88.00',
        proofHash: str_repeat('b', 64),
    );

    $noPermService->approve(
        submissionId: $target->id,
        reviewerId: 403,
        expectedLockVersion: 1,
        idempotencyKey: 'noperm-001',
        externalReference: 'TXN-002',
        verifiedAmount: '88.00',
        verifiedPaidAt: '2026-06-29T10:30:00+08:00',
    );
});

// 6) 锁版本不匹配。
sr040_expect_error('lock_version_mismatch', function () use ($service, $repository): void {
    $target = sr040_make_under_review_submission(
        repository: $repository,
        submissionKey: '33333333-3333-4333-8333-333333333333',
        orderId: 603,
        expectedAmount: '20.00',
        proofHash: str_repeat('c', 64),
    );

    $service->approve(
        submissionId: $target->id,
        reviewerId: 401,
        expectedLockVersion: 0,
        idempotencyKey: 'lock-mismatch',
        externalReference: 'TXN-003',
        verifiedAmount: '20.00',
        verifiedPaidAt: '2026-06-29T10:30:00+08:00',
    );
});

// 7) 验证金额不一致。
sr040_expect_error('amount_mismatch', function () use ($service, $repository): void {
    $target = sr040_make_under_review_submission(
        repository: $repository,
        submissionKey: '44444444-4444-4444-8444-444444444444',
        orderId: 604,
        expectedAmount: '33.00',
        proofHash: str_repeat('d', 64),
    );

    $service->approve(
        submissionId: $target->id,
        reviewerId: 401,
        expectedLockVersion: 1,
        idempotencyKey: 'amount-mismatch',
        externalReference: 'TXN-004',
        verifiedAmount: '34.00',
        verifiedPaidAt: '2026-06-29T10:30:00+08:00',
    );
});

// 8) 已完成提交重复指纹冲突。
$repoDup = new InMemoryPaymentSubmissionRepository;
$serviceDup = new PaymentReviewService(
    repository: $repoDup,
    approvalService: new PaymentApprovalService(new ManualPaymentSubmissionStateMachine),
    completeOrder: static fn (): bool => true,
    canApprove: static fn (int $reviewerId, PaymentSubmission $candidate): bool => true,
    nowProvider: static fn (): string => '2026-06-29T10:08:00+08:00',
);

$dupA = sr040_make_under_review_submission(
    repository: $repoDup,
    submissionKey: '55555555-5555-4555-8555-555555555555',
    orderId: 605,
    expectedAmount: '200.00',
    proofHash: str_repeat('e', 64),
    accountKey: 'acct-A',
);
$dupB = sr040_make_under_review_submission(
    repository: $repoDup,
    submissionKey: '66666666-6666-4666-8666-666666666666',
    orderId: 606,
    expectedAmount: '200.00',
    proofHash: str_repeat('f', 64),
    accountKey: 'acct-A',
);

$serviceDup->approve(
    submissionId: $dupA->id,
    reviewerId: 401,
    expectedLockVersion: 1,
    idempotencyKey: 'dup-first',
    externalReference: 'DUP-TXN',
    verifiedAmount: '200.00',
    verifiedPaidAt: '2026-06-29T10:20:00+08:00',
);

sr040_expect_error('duplicate_transaction_fingerprint', function () use ($serviceDup, $dupB): void {
    $serviceDup->approve(
        submissionId: $dupB->id,
        reviewerId: 401,
        expectedLockVersion: 1,
        idempotencyKey: 'dup-second',
        externalReference: 'DUP-TXN',
        verifiedAmount: '200.00',
        verifiedPaidAt: '2026-06-29T10:20:00+08:00',
    );
});

// 9) 日期格式不正确。
sr040_expect_error('invalid_verified_paid_at', function () use ($service, $repository): void {
    $target = sr040_make_under_review_submission(
        repository: $repository,
        submissionKey: '77777777-7777-4777-8777-777777777777',
        orderId: 607,
        expectedAmount: '77.00',
        proofHash: str_repeat('7', 64),
    );

    $service->approve(
        submissionId: $target->id,
        reviewerId: 401,
        expectedLockVersion: 1,
        idempotencyKey: 'bad-time',
        externalReference: 'TXN-007',
        verifiedAmount: '77.00',
        verifiedPaidAt: 'not-a-time',
    );
});

// 10) 完成订单回调失败。
$repoFail = new InMemoryPaymentSubmissionRepository;
$failService = new PaymentReviewService(
    repository: $repoFail,
    approvalService: new PaymentApprovalService(new ManualPaymentSubmissionStateMachine),
    completeOrder: static fn () => throw new RuntimeException('mocked completion failure'),
    canApprove: static fn (int $reviewerId, PaymentSubmission $candidate): bool => true,
    nowProvider: static fn (): string => '2026-06-29T10:08:00+08:00',
);

$target = sr040_make_under_review_submission(
    repository: $repoFail,
    submissionKey: '88888888-8888-4888-8888-888888888888',
    orderId: 608,
    expectedAmount: '80.00',
    proofHash: str_repeat('8', 64),
);

sr040_expect_error('complete_order_failed', function () use ($failService, $target): void {
    $failService->approve(
        submissionId: $target->id,
        reviewerId: 401,
        expectedLockVersion: 1,
        idempotencyKey: 'complete-failed',
        externalReference: 'TXN-008',
        verifiedAmount: '80.00',
        verifiedPaidAt: '2026-06-29T10:30:00+08:00',
    );
});

sr040_assert(count($completeRecords) >= 2, 'completion callback is invoked on first approval and replay.');

echo "SR-040 payment review service checks passed.\n";
