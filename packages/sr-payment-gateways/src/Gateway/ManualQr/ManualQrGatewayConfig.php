<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Gateway\ManualQr;

final readonly class ManualQrGatewayConfig
{
    public function __construct(
        public bool $manualPaymentEnabled,
        public string $qrChannel,
        public string $accountLabel,
        public string $instructionsVersion,
    ) {}
}
