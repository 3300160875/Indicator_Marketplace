<?php

declare(strict_types=1);

use StockResource\PaymentGateways\Application\Decision\DecisionService;
use StockResource\PaymentGateways\Application\Decision\PaymentDecisionException;
use StockResource\PaymentGateways\Submission\InMemoryPaymentSubmissionRepository;
use StockResource\PaymentGateways\Submission\PaymentSubmission;
use StockResource\PaymentGateways\Submission\PaymentSubmissionStateMachine;

$root = dirname(__DIR__, 3);
$package = $root . '/packages/sr-payment-gateways';
$theme = $root . '/web/app/themes/stock-resource-theme';

foreach ([
    '/src/Submission/PaymentSubmission.php',
    '/src/Submission/SubmissionState.php',
    '/src/Submission/PaymentSubmissionException.php',
    '/src/Submission/PaymentSubmissionRepository.php',
    '/src/Submission/PaymentSubmissionStateMachine.php',
    '/src/Submission/InMemoryPaymentSubmissionRepository.php',
    '/src/Application/Decision/DecisionService.php',
    '/src/Gateway/ManualQr/TransactionFingerprint.php',
    '/src/Gateway/ManualQr/PaymentSubmission.php',
    '/src/Gateway/ManualQr/PaymentSubmissionStateMachine.php',
] as $sourceFile) {
    require_once $package . $sourceFile;
}
require_once $theme . '/components/helpers.php';
require_once $theme . '/components/payment-timeline/timeline.php';

function sr041_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr041_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

function sr041_expect_error(string $codeName, callable $callback): void
{
    try {
        $callback();
    } catch (PaymentDecisionException $exception) {
        if ($exception->codeName === $codeName) {
            return;
        }

        throw new RuntimeException('Expected ' . $codeName . ' but got ' . $exception->codeName . '.');
    }

    throw new RuntimeException('Expected exception ' . $codeName);
}

function sr041_make_under_review_submission(
    InMemoryPaymentSubmissionRepository $repository,
    string $submissionKey,
    int $submissionId,
    int $orderId,
    string $expectedAmount,
    string $proofHash,
): PaymentSubmission {
    $submission = PaymentSubmission::create(
        submissionKey: $submissionKey,
        orderId: $orderId,
        userId: 9000 + $orderId,
        channel: 'alipay',
        expectedAmount: $expectedAmount,
        reportedAmount: $expectedAmount,
        reportedPaidAt: '2026-06-29 10:00:00',
        proofStorageKey: 'proof/' . $orderId . '/' . (9000 + $orderId) . '/' . str_repeat('0', 4) . '.png',
        proofSha256: $proofHash,
        proofMimeType: 'image/png',
        proofFileSize: 1024,
        nowUtc: '2026-06-29 10:00:00',
        externalReference: 'BANK-INIT-' . $submissionId,
    );
    $stored = $repository->create($submission);
    $stateMachine = new PaymentSubmissionStateMachine;

    $underReview = $stateMachine->transition($stored, 'claim_review', $stored->lockVersion);

    return $repository->update($underReview, $stored->lockVersion);
}

$repository = new InMemoryPaymentSubmissionRepository;

$service = new DecisionService(
    repository: $repository,
    stateMachine: new PaymentSubmissionStateMachine,
    canDecide: static fn (int $reviewerId, PaymentSubmission $submission): bool => in_array($reviewerId, [401, 402], true),
    nowProvider: static fn (): string => '2026-06-29T10:05:00+08:00',
);

// 1) 补充材料：在 under_review 下成功写入非公开决议，不覆盖凭证。
$submission = sr041_make_under_review_submission(
    $repository,
    '11111111-1111-4111-8111-111111111111',
    701,
    801,
    '59.00',
    str_repeat('a', 64),
);

$needMore = $service->requestMoreInfo(
    submissionId: $submission->id,
    reviewerId: 401,
    expectedLockVersion: $submission->lockVersion,
    decisionCode: 'NEEDS_MORE_INFO',
    internalNote: '财务审核未通过：请补充',
    userMessage: '请补充付款截图与参考单号',
);

sr041_same('needs_more_info', $needMore->state->value, 'requestMoreInfo should update state to needs_more_info');
sr041_same('NEEDS_MORE_INFO', $needMore->decisionCode, 'default/explicit decision code is normalized');
sr041_same('BANK-INIT-701', $needMore->externalReference, 'decision does not replace proof identity external reference');
sr041_same(401, $needMore->reviewerId, 'requestMoreInfo records reviewer');
sr041_assert(str_starts_with($needMore->proofStorageKey, 'proof/'), 'proof storage key untouched');

// 2) 只有标准原因码可接受。
sr041_expect_error('invalid_decision_code', function () use ($service, $repository): void {
    $needMoreLike = sr041_make_under_review_submission(
        $repository,
        '33333333-3333-4333-8333-333333333333',
        703,
        803,
        '29.00',
        str_repeat('c', 64),
    );

    $service->reject(
        submissionId: $needMoreLike->id,
        reviewerId: 401,
        expectedLockVersion: $needMoreLike->lockVersion,
        decisionCode: 'manual_check',
        userMessage: '非标准码',
    );
});

// 3) 非 under_review 状态不可执行决策。
sr041_expect_error('state_conflict', function () use ($service, $needMore): void {
    $service->reject(
        submissionId: $needMore->id,
        reviewerId: 401,
        expectedLockVersion: $needMore->lockVersion,
        decisionCode: 'RISK_REVIEW',
        userMessage: '驳回',
    );
});

// 4) 拒绝：新提交可以在 under_review 直接驳回。
$rejectTarget = sr041_make_under_review_submission(
    $repository,
    '44444444-4444-4444-8444-444444444444',
    704,
    804,
    '89.00',
    str_repeat('d', 64),
);

$rejected = $service->reject(
    submissionId: $rejectTarget->id,
    reviewerId: 402,
    expectedLockVersion: $rejectTarget->lockVersion,
    decisionCode: 'RISK_REVIEW',
    internalNote: '可疑交易',
    userMessage: '未能确认付款，请重新下单',
);

sr041_same('rejected', $rejected->state->value, 'reject should mark submission as rejected');
sr041_same('RISK_REVIEW', $rejected->decisionCode, 'reject uses standardized decision code');
sr041_same('可疑交易', $rejected->internalNote, 'internal note is persisted for admin-only usage');
sr041_same(null, $rejected->proofDeletedAt, 'proof reference is kept, not cleared');

// 5) 决策服务不向非授权用户放行。
sr041_expect_error('permission_denied', function () use ($repository): void {
    $submission = sr041_make_under_review_submission(
        $repository,
        '22222222-2222-4222-8222-222222222222',
        702,
        802,
        '88.00',
        str_repeat('b', 64),
    );

    $service = new DecisionService(
        repository: $repository,
        stateMachine: new PaymentSubmissionStateMachine,
        canDecide: static fn (int $reviewerId, PaymentSubmission $submission): bool => false,
        nowProvider: static fn (): string => '2026-06-29T11:00:00+08:00',
    );

    $service->requestMoreInfo(
        submissionId: $submission->id,
        reviewerId: 999,
        expectedLockVersion: $submission->lockVersion,
        decisionCode: 'NEEDS_MORE_INFO',
    );
});

// 6) 时间线组件只展示用户消息，不露内部备注。
$timelineHtml = sr_theme_payment_timeline([
    [
        'id' => 1,
        'order_id' => 801,
        'state' => 'needs_more_info',
        'channel' => 'alipay',
        'expected_amount' => '59.00',
        'reported_amount' => '59.00',
        'reviewed_at' => '2026-06-29T10:05:00+08:00',
        'user_message' => '请补充付款流水的最后 4 位',
        'internal_note' => '请财务检查对公账户',
        'submitted_at' => '2026-06-29T10:00:00+08:00',
    ],
]);
sr041_assert(str_contains($timelineHtml, '待补充材料'), 'timeline displays needs_more_info state');
sr041_assert(str_contains($timelineHtml, '请补充付款流水的最后 4 位'), 'timeline displays user-visible decision message');
sr041_assert(! str_contains($timelineHtml, '请财务检查对公账户'), 'timeline hides internal notes from users');

echo "SR-041 payment decision checks passed.\n";
