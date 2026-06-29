<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Checkout;

final readonly class CheckoutRequest
{
    /**
     * @param  list<array<string, int|string>>  $lineItems
     */
    public function __construct(
        public ?int $userId,
        public string $returnUrl,
        public string $serverTotal,
        public string $clientTotal,
        public string $currency,
        public bool $termsAccepted,
        public bool $digitalDeliveryAccepted,
        public CheckoutTerms $terms,
        public array $lineItems,
    ) {}
}
