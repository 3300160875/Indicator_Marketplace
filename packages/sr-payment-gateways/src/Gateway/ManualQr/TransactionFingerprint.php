<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Gateway\ManualQr;

final class TransactionFingerprint
{
    public static function fromBill(
        string $channel,
        string $accountKey,
        string $externalReference,
        string $amount,
        string $paidAt,
    ): string {
        $payload = [
            'account_key' => trim($accountKey),
            'amount' => trim($amount),
            'channel' => trim(strtolower($channel)),
            'external_reference' => trim($externalReference),
            'paid_at' => trim($paidAt),
        ];
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
