<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Gateway\ManualQr;

final readonly class ManualQrPaymentRequest
{
    public function __construct(
        public int $orderId,
        public int $userId,
        public string $amount,
        public string $currency,
        public string $idempotencyKey,
    ) {}
}
