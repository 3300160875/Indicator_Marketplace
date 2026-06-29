<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Submission;

use RuntimeException;

final class PaymentSubmissionException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }

    public static function notFound(int $submissionId): self
    {
        return new self('submission_not_found', 'Submission '.$submissionId.' not found.');
    }

    public static function invalidState(string $state): self
    {
        return new self('invalid_state', 'Unsupported submission state: '.$state.'.');
    }

    public static function lockVersionMismatch(int $expected, int $actual): self
    {
        return new self('lock_version_mismatch', 'Expected lock version '.$expected.', got '.$actual.'.');
    }

    public static function duplicateSubmissionKey(string $submissionKey): self
    {
        return new self('duplicate_submission_key', 'Submission key '.$submissionKey.' already exists with different payload.');
    }

    public static function duplicateTransactionFingerprint(string $fingerprint): self
    {
        return new self('duplicate_transaction_fingerprint', 'Transaction fingerprint '.$fingerprint.' already exists.');
    }

    public static function invalidTransition(string $state, string $action): self
    {
        return new self('invalid_transition', 'Cannot apply action '.$action.' from '.$state.'.');
    }
}
