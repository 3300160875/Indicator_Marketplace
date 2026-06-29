<?php

declare(strict_types=1);

use StockResource\PaymentGateways\Gateway\ManualQr\ManualQrException;
use StockResource\PaymentGateways\Gateway\ManualQr\ManualQrGateway;
use StockResource\PaymentGateways\Gateway\ManualQr\ManualQrGatewayConfig;
use StockResource\PaymentGateways\Gateway\ManualQr\ManualQrPaymentRequest;
use StockResource\PaymentGateways\Gateway\ManualQr\PaymentApprovalService;
use StockResource\PaymentGateways\Gateway\ManualQr\PaymentSubmission;
use StockResource\PaymentGateways\Gateway\ManualQr\PaymentSubmissionStateMachine;
use StockResource\PaymentGateways\Gateway\ManualQr\TransactionFingerprint;

$root = dirname(__DIR__, 3);
$gatewayPackage = $root.'/packages/sr-payment-gateways';

foreach ([
    '/src/Gateway/ManualQr/ManualQrException.php',
    '/src/Gateway/ManualQr/ManualQrGatewayConfig.php',
    '/src/Gateway/ManualQr/ManualQrPaymentRequest.php',
    '/src/Gateway/ManualQr/ManualQrGateway.php',
    '/src/Gateway/ManualQr/PaymentSubmission.php',
    '/src/Gateway/ManualQr/PaymentSubmissionStateMachine.php',
    '/src/Gateway/ManualQr/TransactionFingerprint.php',
    '/src/Gateway/ManualQr/PaymentApprovalService.php',
] as $sourceFile) {
    require_once $gatewayPackage.$sourceFile;
}

function sr036_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr036_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr036_expect_error(string $codeName, callable $callback): void
{
    try {
        $callback();
    } catch (ManualQrException $exception) {
        sr036_same($codeName, $exception->codeName, 'manual qr exception code');

        return;
    }

    throw new RuntimeException('Expected manual qr exception '.$codeName);
}

$disabled = new ManualQrGateway(new ManualQrGatewayConfig(
    manualPaymentEnabled: false,
    qrChannel: 'alipay',
    accountLabel: '平台人工收款账户',
    instructionsVersion: 'manual-qr-2026-06',
));
$request = new ManualQrPaymentRequest(
    orderId: 8801,
    userId: 200,
    amount: '106.20',
    currency: 'CNY',
    idempotencyKey: 'checkout-8801-v1',
);
sr036_expect_error('payment_disabled', fn () => $disabled->createIntent($request));

$gateway = new ManualQrGateway(new ManualQrGatewayConfig(
    manualPaymentEnabled: true,
    qrChannel: 'alipay',
    accountLabel: '平台人工收款账户',
    instructionsVersion: 'manual-qr-2026-06',
));
$intent = $gateway->createIntent($request);
sr036_same('manual_qr', $intent['gateway_id'], 'gateway id');
sr036_same('pending', $intent['edd_order_status'], 'manual QR gateway leaves EDD order pending');
sr036_same('manual-qr-2026-06', $intent['instructions_version'], 'instructions version is captured');
sr036_assert(str_contains($intent['notice'], '人工核验'), 'notice explains manual review');
sr036_assert(str_contains($intent['notice'], '不会自动到账'), 'notice says it is not automatic payment recognition');
sr036_same('checkout-8801-v1', $intent['idempotency_key'], 'idempotency key is preserved');

$machine = new PaymentSubmissionStateMachine;
$submission = PaymentSubmission::draft(
    orderId: 8801,
    userId: 200,
    amount: '106.20',
    currency: 'CNY',
    proofHash: 'sha256-proof',
);
sr036_same('draft', $submission->state, 'new submission starts draft');
$submitted = $machine->transition($submission, 'submit', expectedLockVersion: 0);
sr036_same('submitted', $submitted->state, 'draft can submit');
sr036_same(1, $submitted->lockVersion, 'submit bumps lock version');
$underReview = $machine->transition($submitted, 'claim_review', expectedLockVersion: 1);
sr036_same('under_review', $underReview->state, 'submitted can be claimed');
sr036_same(2, $underReview->lockVersion, 'claim bumps lock version');
sr036_expect_error('invalid_transition', fn () => $machine->transition($submitted, 'approve', expectedLockVersion: 1));
sr036_expect_error('lock_version_mismatch', fn () => $machine->transition($submitted, 'claim_review', expectedLockVersion: 0));

$fingerprintA = TransactionFingerprint::fromBill(
    channel: 'alipay',
    accountKey: 'account-a',
    externalReference: 'BILL-20260629-1',
    amount: '106.20',
    paidAt: '2026-06-29T10:00:00+08:00',
);
$fingerprintB = TransactionFingerprint::fromBill(
    channel: 'alipay',
    accountKey: 'account-a',
    externalReference: 'BILL-20260629-1',
    amount: '106.20',
    paidAt: '2026-06-29T10:00:00+08:00',
);
sr036_same($fingerprintA, $fingerprintB, 'same bill creates stable unique fingerprint');
sr036_assert(strlen($fingerprintA) === 64, 'fingerprint is sha256 hex');

$approval = new PaymentApprovalService($machine);
sr036_expect_error('real_bill_required', fn () => $approval->approve(
    submission: $underReview,
    expectedLockVersion: 2,
    idempotencyKey: 'approve-8801',
    proofHash: 'sha256-proof',
    billFingerprint: null,
    billAmount: null,
));
$approved = $approval->approve(
    submission: $underReview,
    expectedLockVersion: 2,
    idempotencyKey: 'approve-8801',
    proofHash: 'sha256-proof',
    billFingerprint: $fingerprintA,
    billAmount: '106.20',
);
sr036_same('approved', $approved['submission']->state, 'verified bill can approve');
sr036_same(3, $approved['submission']->lockVersion, 'approval bumps lock version');
sr036_same(false, $approved['complete_edd_order'], 'gateway layer does not auto-complete EDD order');
sr036_same('approve-8801', $approved['idempotency_key'], 'approval result records idempotency key');
$replayed = $approval->approve(
    submission: $approved['submission'],
    expectedLockVersion: 3,
    idempotencyKey: 'approve-8801',
    proofHash: 'sha256-proof',
    billFingerprint: $fingerprintA,
    billAmount: '106.20',
);
sr036_same($approved['submission']->lockVersion, $replayed['submission']->lockVersion, 'idempotent replay does not bump lock version');
sr036_same(true, $replayed['idempotent_replay'], 'idempotent replay is marked');
sr036_expect_error('lock_version_mismatch', fn () => $approval->approve(
    submission: $approved['submission'],
    expectedLockVersion: 2,
    idempotencyKey: 'approve-8801',
    proofHash: 'sha256-proof',
    billFingerprint: $fingerprintA,
    billAmount: '106.20',
));
sr036_expect_error('real_bill_required', fn () => $approval->approve(
    submission: $approved['submission'],
    expectedLockVersion: 3,
    idempotencyKey: 'approve-8801',
    proofHash: '',
    billFingerprint: null,
    billAmount: null,
));
sr036_expect_error('amount_mismatch', fn () => $approval->approve(
    submission: $underReview,
    expectedLockVersion: 2,
    idempotencyKey: 'approve-8801-mismatch',
    proofHash: 'sha256-proof',
    billFingerprint: $fingerprintA,
    billAmount: '100.00',
));

$source = '';
foreach (glob($gatewayPackage.'/src/Gateway/ManualQr/*.php') ?: [] as $file) {
    $source .= (string) file_get_contents($file)."\n";
}
foreach (['$_POST', '$_REQUEST', 'wpdb', 'SELECT ', 'edd_update_payment_status', 'edd_complete_purchase'] as $forbidden) {
    sr036_assert(! str_contains($source, $forbidden), 'manual QR gateway avoids direct request/db/EDD completion access: '.$forbidden);
}

echo "SR-036 manual QR gateway checks passed.\n";
