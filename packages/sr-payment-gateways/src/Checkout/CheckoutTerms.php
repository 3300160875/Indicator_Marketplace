<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Checkout;

final readonly class CheckoutTerms
{
    public function __construct(
        public string $serviceTermsVersion,
        public string $digitalDeliveryVersion,
        public string $privacyVersion,
        public string $refundRuleVersion,
    ) {}

    /**
     * @return array{service_terms_version:string,digital_delivery_version:string,privacy_version:string,refund_rule_version:string,confirmed_at:string}
     */
    public function snapshot(?string $confirmedAt = null): array
    {
        return [
            'service_terms_version' => $this->serviceTermsVersion,
            'digital_delivery_version' => $this->digitalDeliveryVersion,
            'privacy_version' => $this->privacyVersion,
            'refund_rule_version' => $this->refundRuleVersion,
            'confirmed_at' => $confirmedAt ?? gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }
}
