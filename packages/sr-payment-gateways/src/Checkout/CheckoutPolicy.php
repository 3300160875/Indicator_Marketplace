<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Checkout;

final readonly class CheckoutPolicy
{
    public function __construct(
        private bool $manualPaymentEnabled,
        private bool $gate0Approved,
        private string $loginBaseUrl,
    ) {}

    /**
     * @return array{allowed:false,reason:'login_required',login_url:string}
     */
    public function guestDecision(string $returnUrl): array
    {
        return [
            'allowed' => false,
            'reason' => 'login_required',
            'login_url' => $this->loginBaseUrl.'?redirect_to='.rawurlencode($returnUrl),
        ];
    }

    public function assertCanCreateOrder(CheckoutRequest $request): void
    {
        if ($request->userId === null || $request->userId < 1) {
            throw CheckoutException::loginRequired();
        }

        if (! $this->manualPaymentEnabled || ! $this->gate0Approved) {
            throw CheckoutException::paymentDisabled();
        }

        if (! $request->termsAccepted) {
            throw CheckoutException::termsNotAccepted();
        }

        if (! $request->digitalDeliveryAccepted) {
            throw CheckoutException::digitalDeliveryNotAccepted();
        }
    }
}
