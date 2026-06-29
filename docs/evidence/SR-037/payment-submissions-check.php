<?php

declare(strict_types=1);

use StockResource\PaymentGateways\Submission\InMemoryPaymentSubmissionRepository;
use StockResource\PaymentGateways\Submission\PaymentSubmission;
use StockResource\PaymentGateways\Submission\PaymentSubmissionException;
use StockResource\PaymentGateways\Submission\PaymentSubmissionSchemaMigration;
use StockResource\PaymentGateways\Submission\PaymentSubmissionStateMachine;
use StockResource\PaymentGateways\Submission\SubmissionState;
use StockResource\PaymentGateways\Submission\TransactionFingerprint;

$root = dirname(__DIR__, 3);
$package = $root.'/packages/sr-payment-gateways';
$core = $root.'/packages/sr-core';

foreach ([
    '/src/Infrastructure/Migration/Migration.php',
    '/src/Submission/PaymentSubmission.php',
    '/src/Submission/PaymentSubmissionException.php',
    '/src/Submission/PaymentSubmissionSchemaMigration.php',
    '/src/Submission/PaymentSubmissionStateMachine.php',
    '/src/Submission/PaymentSubmissionRepository.php',
    '/src/Submission/InMemoryPaymentSubmissionRepository.php',
    '/src/Submission/SubmissionState.php',
    '/src/Submission/TransactionFingerprint.php',
] as $sourceFile) {
    if (str_starts_with($sourceFile, '/src/Infrastructure/')) {
        require_once $core.$sourceFile;

        continue;
    }

    require_once $package.$sourceFile;
}

function sr037_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr037_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr037_expect_error(string $codeName, callable $callback): void
{
    try {
        $callback();
    } catch (PaymentSubmissionException $exception) {
        sr037_same($codeName, $exception->codeName, 'payment submission exception code');

        return;
    }

    throw new RuntimeException('Expected payment submission exception '.$codeName);
}

function sr037_expect_runtime_error(string $messagePart, callable $callback): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        if (! str_contains($exception->getMessage(), $messagePart)) {
            throw new RuntimeException(
                'Expected runtime error message to contain '.$messagePart.' but got '.$exception->getMessage()
            );
        }

        return;
    }

    throw new RuntimeException('Expected runtime error containing '.$messagePart);
}

$now = '2026-06-29 10:00:00';
$submission = PaymentSubmission::create(
    submissionKey: '11111111-1111-4111-8111-111111111111',
    orderId: 2201,
    userId: 3001,
    channel: 'alipay-manual',
    expectedAmount: '99.00',
    reportedAmount: '99.00',
    reportedPaidAt: $now,
    proofStorageKey: 'sr-payments/2026/06/29/submission-1.bin',
    proofSha256: str_repeat('a', 64),
    proofMimeType: 'image/png',
    proofFileSize: 2048,
    nowUtc: $now,
    accountKey: 'acct-001',
    payerNote: 'first note',
    externalReference: 'BILL-20260629-001',
);

sr037_same(SubmissionState::Submitted, $submission->state, 'submission starts in submitted state');
sr037_same(0, $submission->lockVersion, 'new submission has lock version 0');
sr037_same('CNY', $submission->currency, 'default currency');
sr037_assert($submission->idempotencyHash() !== '', 'submission idempotency hash is non-empty');
sr037_expect_error('invalid_state', fn () => PaymentSubmission::fromArray(['state' => 'bad']));
sr037_expect_runtime_error('submission_key must be a UUID v1-v5 string.', fn () => PaymentSubmission::create(
    submissionKey: 'bad-key',
    orderId: 2201,
    userId: 3001,
    channel: 'alipay',
    expectedAmount: '99.00',
    reportedAmount: '99.00',
    reportedPaidAt: $now,
    proofStorageKey: 'sr-payments/2026/06/29/2.bin',
    proofSha256: str_repeat('b', 64),
    proofMimeType: 'image/png',
    proofFileSize: 100,
    nowUtc: $now,
));

$stateMachine = new PaymentSubmissionStateMachine;
$underReview = $stateMachine->transition($submission, 'claim_review', 0);
sr037_same(1, $underReview->lockVersion, 'claim review bumps lock version');
sr037_same(SubmissionState::UnderReview, $underReview->state, 'submitted can be claimed to under_review');
sr037_expect_error('lock_version_mismatch', fn () => $stateMachine->transition($underReview, 'approve', 0));
$approved = $stateMachine->transition(
    submission: $underReview,
    action: 'approve',
    expectedLockVersion: 1,
);
sr037_expect_error('invalid_transition', fn () => $stateMachine->transition($approved, 'claim_review', 2));
sr037_same(SubmissionState::Approved, $approved->state, 'under_review can approve');
sr037_same(2, $approved->lockVersion, 'approve bumps lock version');
sr037_assert($approved->lockVersion > 0, 'approved lock version is positive');

$repository = new InMemoryPaymentSubmissionRepository;
$stored = $repository->create($submission);
sr037_same(1, $stored->id, 'repository assigns first id');

$replay = $repository->create(PaymentSubmission::fromArray($submission->toArray()));
sr037_same($stored->id, $replay->id, 'same submission_key and payload returns idempotent existing record');

$submissionConflict = PaymentSubmission::fromArray(array_merge(
    $submission->toArray(),
    [
        'id' => 0,
        'proof_sha256' => str_repeat('c', 64),
    ],
));
sr037_expect_error('duplicate_submission_key', fn () => $repository->create($submissionConflict));

$another = PaymentSubmission::create(
    submissionKey: '22222222-2222-4222-8222-222222222222',
    orderId: 2202,
    userId: 3002,
    channel: 'wechat-manual',
    expectedAmount: '88.80',
    reportedAmount: '88.80',
    reportedPaidAt: $now,
    proofStorageKey: 'sr-payments/2026/06/29/another.bin',
    proofSha256: str_repeat('d', 64),
    proofMimeType: 'image/jpeg',
    proofFileSize: 4096,
    nowUtc: $now,
);
$fingerprintA = TransactionFingerprint::fromBill(
    channel: 'wechat',
    accountKey: 'acct-02',
    externalReference: 'BILL-20260629-888',
    amount: '88.80',
    paidAt: '2026-06-29T11:00:00+08:00',
);
$another = $another->withVerification($fingerprintA, '88.80', $now);
$repository->create($another);

$anotherSameFingerprint = PaymentSubmission::create(
    submissionKey: '33333333-3333-4333-8333-333333333333',
    orderId: 2203,
    userId: 3003,
    channel: 'wechat-manual',
    expectedAmount: '88.80',
    reportedAmount: '88.80',
    reportedPaidAt: $now,
    proofStorageKey: 'sr-payments/2026/06/29/other.bin',
    proofSha256: str_repeat('e', 64),
    proofMimeType: 'image/jpeg',
    proofFileSize: 1024,
    nowUtc: $now,
);
$anotherSameFingerprint = $anotherSameFingerprint->withVerification(
    transactionFingerprint: $fingerprintA,
    verifiedAmount: '88.80',
    verifiedPaidAt: $now,
);
sr037_expect_error('duplicate_transaction_fingerprint', fn () => $repository->create($anotherSameFingerprint));

// Negative/invalid input guards.
sr037_expect_runtime_error('must be a decimal with up to two places', fn () => PaymentSubmission::create(
    submissionKey: '44444444-4444-4444-8444-444444444444',
    orderId: 2204,
    userId: 3004,
    channel: 'alipay',
    expectedAmount: '-1.00',
    reportedAmount: '88.00',
    reportedPaidAt: $now,
    proofStorageKey: 'sr-payments/2026/06/29/invalid.bin',
    proofSha256: str_repeat('f', 64),
    proofMimeType: 'image/png',
    proofFileSize: 1000,
    nowUtc: $now,
));

$submissionForOrder = $repository->findByOrder(2201);
sr037_same(1, count($submissionForOrder), 'findByOrder returns expected number');
sr037_same($stored->id, $submissionForOrder[0]->id, 'findByOrder returns stable id');

$foundByKey = $repository->findBySubmissionKey($submission->submissionKey);
sr037_same($stored->submissionKey, $foundByKey?->submissionKey, 'findBySubmissionKey works');

$foundByFingerprint = $repository->findByTransactionFingerprint($fingerprintA);
sr037_same($another->submissionKey, $foundByFingerprint?->submissionKey, 'findByTransactionFingerprint works');

sr037_expect_error('lock_version_mismatch', fn () => $repository->update($stateMachine->transition($stored, 'claim_review', 0), 2));
$underReview = $repository->update($stateMachine->transition($stored, 'claim_review', 0), 0);
sr037_same(1, $underReview->lockVersion, 'repository update keeps lock version');

$needsMoreInfo = $stateMachine->transition(
    submission: $underReview,
    action: 'need_more_info',
    expectedLockVersion: 1,
);
$needsMoreInfo = $needsMoreInfo->withState(
    state: SubmissionState::NeedsMoreInfo,
    reviewerId: 901,
    decisionCode: 'NEED_MORE_INFO',
    userMessage: 'please upload clearer image',
);
$updated = $repository->update($needsMoreInfo, $underReview->lockVersion);
sr037_same(3, $updated->lockVersion, 'repository update keeps optimistic lock increments');
sr037_same(901, $updated->reviewerId, 'update can carry reviewer id');
sr037_same('NEED_MORE_INFO', $updated->decisionCode, 'update can carry decision code');

$migration = PaymentSubmissionSchemaMigration::create();
sr037_same('202606290001', $migration->version(), 'migration version');
sr037_same('sr_payment_submissions', $migration->tableName(), 'migration target table');
sr037_same('Create payment submissions table.', $migration->description(), 'migration description');
sr037_assert(preg_match('/^[a-f0-9]{64}$/i', $migration->checksum()) === 1, 'migration checksum is sha256');

$migrated = $migration->sql('wp_');
sr037_assert(str_contains($migrated, 'CREATE TABLE wp_sr_payment_submissions ('), 'migration targets prefixed table name');
sr037_assert(str_contains($migrated, 'uq_submission_key'), 'migration defines uq_submission_key');
sr037_assert(str_contains($migrated, 'uq_transaction_fingerprint'), 'migration defines uq_transaction_fingerprint');
sr037_assert(str_contains($migrated, 'reported_amount DECIMAL(12,2)'), 'migration uses DECIMAL amount');
sr037_assert(str_contains($migrated, 'proof_storage_key VARCHAR(512)'), 'migration stores proof storage key');

$upSql = $migration->up();
sr037_same(1, count($upSql), 'migration up returns one statement');

echo "SR-037 payment submission checks passed.\n";
