<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Submission;

use RuntimeException;

final class InMemoryPaymentSubmissionRepository implements PaymentSubmissionRepository
{
    /** @var array<int, PaymentSubmission> */
    private array $submissions = [];

    /** @var array<string, int> */
    private array $bySubmissionKey = [];

    /** @var array<string, int> */
    private array $byTransactionFingerprint = [];

    private int $nextId = 1;

    public function create(PaymentSubmission $submission): PaymentSubmission
    {
        if ($submission->id !== 0) {
            throw new RuntimeException('create() accepts only unsaved payment submissions.');
        }

        $existingByKey = $this->findBySubmissionKey($submission->submissionKey);
        if ($existingByKey !== null) {
            if ($existingByKey->idempotencyHash() !== $submission->idempotencyHash()) {
                throw PaymentSubmissionException::duplicateSubmissionKey($submission->submissionKey);
            }

            return $existingByKey;
        }

        if ($submission->transactionFingerprint !== null) {
            $existingByFingerprint = $this->findByTransactionFingerprint($submission->transactionFingerprint);
            if ($existingByFingerprint !== null) {
                throw PaymentSubmissionException::duplicateTransactionFingerprint($submission->transactionFingerprint);
            }
        }

        $stored = $submission->withId($this->nextId++);
        $this->submissions[$stored->id] = $stored;
        $this->bySubmissionKey[$stored->submissionKey] = $stored->id;

        if ($stored->transactionFingerprint !== null) {
            $this->byTransactionFingerprint[$stored->transactionFingerprint] = $stored->id;
        }

        return $stored;
    }

    public function update(PaymentSubmission $submission, int $expectedLockVersion): PaymentSubmission
    {
        if ($submission->id <= 0 || ! isset($this->submissions[$submission->id])) {
            throw PaymentSubmissionException::notFound($submission->id);
        }

        $stored = $this->submissions[$submission->id];
        if ($stored->lockVersion !== $expectedLockVersion) {
            throw PaymentSubmissionException::lockVersionMismatch($expectedLockVersion, $stored->lockVersion);
        }

        if ($submission->lockVersion <= $expectedLockVersion) {
            throw PaymentSubmissionException::lockVersionMismatch($expectedLockVersion, $submission->lockVersion);
        }

        if ($submission->submissionKey !== $stored->submissionKey) {
            throw new RuntimeException('submission_key cannot be changed.');
        }

        if ($stored->transactionFingerprint !== $submission->transactionFingerprint && $submission->transactionFingerprint !== null) {
            $owner = $this->byTransactionFingerprint[$submission->transactionFingerprint] ?? null;
            if ($owner !== null && $owner !== $submission->id) {
                throw PaymentSubmissionException::duplicateTransactionFingerprint($submission->transactionFingerprint);
            }
        }

        if ($stored->transactionFingerprint !== null && $submission->transactionFingerprint === null) {
            unset($this->byTransactionFingerprint[$stored->transactionFingerprint]);
        }

        if ($stored->transactionFingerprint !== $submission->transactionFingerprint && $submission->transactionFingerprint !== null) {
            $this->byTransactionFingerprint[$submission->transactionFingerprint] = $submission->id;
        }

        $this->submissions[$submission->id] = $submission;

        return $submission;
    }

    public function findById(int $id): ?PaymentSubmission
    {
        return $this->submissions[$id] ?? null;
    }

    public function findByOrder(int $orderId): array
    {
        $rows = array_values(array_filter($this->submissions, static fn (PaymentSubmission $submission): bool => $submission->orderId === $orderId));
        usort($rows, static fn (PaymentSubmission $left, PaymentSubmission $right): int => $left->id <=> $right->id);

        return $rows;
    }

    public function findBySubmissionKey(string $submissionKey): ?PaymentSubmission
    {
        $id = $this->bySubmissionKey[$submissionKey] ?? null;

        return $id === null ? null : ($this->submissions[$id] ?? null);
    }

    public function findByTransactionFingerprint(string $fingerprint): ?PaymentSubmission
    {
        $id = $this->byTransactionFingerprint[$fingerprint] ?? null;

        return $id === null ? null : ($this->submissions[$id] ?? null);
    }
}
