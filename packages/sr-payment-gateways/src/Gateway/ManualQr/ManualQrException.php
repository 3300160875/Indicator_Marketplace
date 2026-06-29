<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Gateway\ManualQr;

use RuntimeException;

final class ManualQrException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }

    public static function paymentDisabled(): self
    {
        return new self('payment_disabled', 'Manual QR payment is disabled.');
    }

    public static function invalidTransition(string $state, string $action): self
    {
        return new self('invalid_transition', 'Cannot apply '.$action.' from '.$state.'.');
    }

    public static function lockVersionMismatch(int $expected, int $actual): self
    {
        return new self('lock_version_mismatch', 'Expected lock version '.$expected.', got '.$actual.'.');
    }

    public static function realBillRequired(): self
    {
        return new self('real_bill_required', 'A verified bill fingerprint is required; proof is only a clue.');
    }

    public static function amountMismatch(string $expected, string $actual): self
    {
        return new self('amount_mismatch', 'Expected bill amount '.$expected.', got '.$actual.'.');
    }
}
