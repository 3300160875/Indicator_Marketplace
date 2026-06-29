<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Checkout;

use RuntimeException;

final class CheckoutException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }

    public static function loginRequired(): self
    {
        return new self('login_required', 'Checkout requires a logged-in user.');
    }

    public static function paymentDisabled(): self
    {
        return new self('payment_disabled', 'Payment creation is disabled until Gate 0 and manual payment are enabled.');
    }

    public static function termsNotAccepted(): self
    {
        return new self('terms_not_accepted', 'Checkout terms must be accepted before order creation.');
    }

    public static function digitalDeliveryNotAccepted(): self
    {
        return new self('digital_delivery_not_accepted', 'Digital content delivery confirmation is required.');
    }
}
