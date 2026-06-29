<?php

declare(strict_types=1);

use StockResource\PaymentGateways\Rest\PaymentProofController;
use StockResource\PaymentGateways\Rest\PaymentProofException;
use StockResource\PaymentGateways\Submission\InMemoryPaymentSubmissionRepository;

$root = dirname(__DIR__, 3);
$package = $root.'/packages/sr-payment-gateways';
foreach ([
    '/src/Submission/PaymentSubmission.php',
    '/src/Submission/SubmissionState.php',
    '/src/Submission/PaymentSubmissionException.php',
    '/src/Submission/PaymentSubmissionRepository.php',
    '/src/Submission/PaymentSubmissionStateMachine.php',
    '/src/Submission/InMemoryPaymentSubmissionRepository.php',
    '/src/Rest/PaymentProofController.php',
] as $sourceFile) {
    require_once $package.$sourceFile;
}

function sr038_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr038_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr038_expect_error(string $codeName, callable $callback): void
{
    try {
        $callback();
    } catch (PaymentProofException $exception) {
        if ($exception->codeName === $codeName) {
            return;
        }

        throw new RuntimeException('Expected '.$codeName.' but got '.$exception->codeName.'.');
    }

    throw new RuntimeException('Expected exception '.$codeName);
}

$orders = [
    101 => [
        'id' => 101,
        'user_id' => 9101,
        'status' => 'pending',
        'total' => '59.00',
        'currency' => 'CNY',
        'meta' => ['account_key' => 'acct-101'],
    ],
    102 => [
        'id' => 102,
        'user_id' => 9102,
        'status' => 'complete',
        'total' => '60.00',
        'currency' => 'CNY',
    ],
];

$orderResolver = static fn (int $orderId): ?array => $orders[$orderId] ?? null;

$repository = new InMemoryPaymentSubmissionRepository;
$controller = new PaymentProofController(
    repository: $repository,
    orderResolver: $orderResolver,
    nowProvider: static fn (): string => '2026-06-29 10:00:00',
    idempotencyKeyProvider: static function (int $orderId, int $userId, string $idempotencyKey): string {
        $seed = hash('sha256', $orderId.'|'.$userId.'|'.$idempotencyKey);

        return sprintf(
            '%s-%s-4%s-8%s-%s',
            substr($seed, 0, 8),
            substr($seed, 8, 4),
            substr($seed, 12, 3),
            substr($seed, 15, 3),
            substr($seed, 18, 12),
        );
    },
    allowedStatuses: ['pending'],
);

// 空的 idempotency key 被拒绝。
sr038_expect_error('invalid_idempotency_key', function () use ($controller): void {
    $controller->submitPaymentProof(
        orderId: 101,
        requestUserId: 9101,
        idempotencyKey: '',
        payload: [],
    );
});

// 订单所有者不匹配。
sr038_expect_error('order_forbidden', function () use ($controller): void {
    $controller->submitPaymentProof(
        orderId: 101,
        requestUserId: 9999,
        idempotencyKey: 'user-mismatch',
        payload: ['channel' => 'alipay', 'reported_amount' => '59.00', 'reported_paid_at' => '2026-06-29T10:00:00+08:00', 'proof' => ['content' => 'x', 'mime_type' => 'image/png']],
    );
});

// 非法状态订单不可提交。
sr038_expect_error('order_not_reviewable', function () use ($controller): void {
    $controller->submitPaymentProof(
        orderId: 102,
        requestUserId: 9102,
        idempotencyKey: 'closed-order',
        payload: ['channel' => 'wechat', 'reported_amount' => '60.00', 'reported_paid_at' => '2026-06-29T10:00:00+08:00', 'proof' => ['content' => 'x', 'mime_type' => 'image/png']],
    );
});

// 不支持的支付通道。
sr038_expect_error('invalid_channel', function () use ($controller): void {
    $controller->submitPaymentProof(
        orderId: 101,
        requestUserId: 9101,
        idempotencyKey: 'bad-channel',
        payload: ['channel' => 'card', 'reported_amount' => '59.00', 'reported_paid_at' => '2026-06-29T10:00:00+08:00', 'proof' => ['content' => 'x', 'mime_type' => 'image/png']],
    );
});

// 格式错误的上报金额。
sr038_expect_error('invalid_reported_amount', function () use ($controller): void {
    $controller->submitPaymentProof(
        orderId: 101,
        requestUserId: 9101,
        idempotencyKey: 'bad-money',
        payload: ['channel' => 'wechat', 'reported_amount' => '-10', 'reported_paid_at' => '2026-06-29T10:00:00+08:00', 'proof' => ['content' => 'x', 'mime_type' => 'image/png']],
    );
});

// 时间格式不合法。
sr038_expect_error('invalid_reported_paid_at', function () use ($controller): void {
    $controller->submitPaymentProof(
        orderId: 101,
        requestUserId: 9101,
        idempotencyKey: 'bad-time',
        payload: ['channel' => 'wechat', 'reported_amount' => '59.00', 'reported_paid_at' => '2026-13-99 99:99:99', 'proof' => ['content' => 'x', 'mime_type' => 'image/png']],
    );
});

// 文件缺失。
sr038_expect_error('missing_proof', function () use ($controller): void {
    $controller->submitPaymentProof(
        orderId: 101,
        requestUserId: 9101,
        idempotencyKey: 'missing-proof',
        payload: ['channel' => 'wechat', 'reported_amount' => '59.00', 'reported_paid_at' => '2026-06-29T10:00:00+08:00'],
    );
});

// 8MiB 上限保护。
$hugeProof = str_repeat('a', 8 * 1024 * 1024 + 1);
sr038_expect_error('invalid_proof_size', function () use ($controller, $hugeProof): void {
    $controller->submitPaymentProof(
        orderId: 101,
        requestUserId: 9101,
        idempotencyKey: 'too-large',
        payload: ['channel' => 'wechat', 'reported_amount' => '59.00', 'reported_paid_at' => '2026-06-29T10:00:00+08:00', 'proof' => ['content' => $hugeProof, 'mime_type' => 'image/png']],
    );
});

// 首次提交成功。
$first = $controller->submitPaymentProof(
    orderId: 101,
    requestUserId: 9101,
    idempotencyKey: 'idem-proof',
    payload: ['channel' => 'wechat', 'reported_amount' => '59.00', 'reported_paid_at' => '2026-06-29T10:00:00+08:00', 'proof' => ['content' => 'abc', 'mime_type' => 'image/png'], 'payer_note' => 'note'],
);

sr038_same('submitted', $first['data']['state'], 'initial submit creates submitted state');
sr038_same(0, $first['data']['lock_version'], 'new submission starts with lock_version 0');

// 幂等键相同、相同内容返回同一提交。
$second = $controller->submitPaymentProof(
    orderId: 101,
    requestUserId: 9101,
    idempotencyKey: 'idem-proof',
    payload: ['channel' => 'wechat', 'reported_amount' => '59.00', 'reported_paid_at' => '2026-06-29T10:00:00+08:00', 'proof' => ['content' => 'abc', 'mime_type' => 'image/png'], 'payer_note' => 'note'],
);

sr038_same($first['data']['id'], $second['data']['id'], 'same idempotency key returns same submission');

// 幂等键相同但 payload 变更应报错。
sr038_expect_error('state_conflict', function () use ($controller): void {
    $controller->submitPaymentProof(
        orderId: 101,
        requestUserId: 9101,
        idempotencyKey: 'idem-proof',
        payload: ['channel' => 'wechat', 'reported_amount' => '60.00', 'reported_paid_at' => '2026-06-29T10:00:00+08:00', 'proof' => ['content' => 'abc', 'mime_type' => 'image/png']],
    );
});

// get payment status 可返回最近提交。
$status = $controller->getPaymentStatus(orderId: 101, requestUserId: 9101);
sr038_same($status['data']['id'], $first['data']['id'], 'payment status returns latest submission');

// proof timeline 包含一条时间线。
$timeline = $controller->proofTimeline(orderId: 101, requestUserId: 9101);
sr038_same(1, count($timeline['data']), 'timeline has one item for created submission');

// 兼容 base64 data URL 的 MIME 获取。
$base64Proof = 'data:image/jpeg;base64,'.base64_encode('jpeg-bytes');
$base64Submit = $controller->submitPaymentProof(
    orderId: 101,
    requestUserId: 9101,
    idempotencyKey: 'base64-proof',
    payload: [
        'channel' => 'wechat',
        'reported_amount' => '59.00',
        'reported_paid_at' => '2026-06-29T11:00:00+08:00',
        'proof' => ['base64' => $base64Proof],
    ],
);

sr038_assert($base64Submit['data']['lock_version'] === 0, 'base64 proof submit succeeds and lock_version is 0');

echo "SR-038 payment proof checks passed.\n";
