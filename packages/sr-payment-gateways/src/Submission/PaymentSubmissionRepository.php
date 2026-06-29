<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Submission;

interface PaymentSubmissionRepository
{
    public function create(PaymentSubmission $submission): PaymentSubmission;

    public function update(PaymentSubmission $submission, int $expectedLockVersion): PaymentSubmission;

    public function findById(int $id): ?PaymentSubmission;

    /**
     * @return list<PaymentSubmission>
     */
    public function findByOrder(int $orderId): array;

    public function findBySubmissionKey(string $submissionKey): ?PaymentSubmission;

    public function findByTransactionFingerprint(string $fingerprint): ?PaymentSubmission;
}
