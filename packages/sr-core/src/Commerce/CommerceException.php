<?php

declare(strict_types=1);

namespace StockResource\Core\Commerce;

use RuntimeException;

final class CommerceException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }

    public static function productTypeMismatch(string $expected, string $actual): self
    {
        return new self('product_type_mismatch', 'Expected product type '.$expected.', got '.$actual.'.');
    }

    public static function accessModeMismatch(string $expected, string $actual): self
    {
        return new self('access_mode_mismatch', 'Expected access mode '.$expected.', got '.$actual.'.');
    }

    public static function priceRequired(int $downloadId, int $priceId): self
    {
        return new self('price_required', 'Server price is required for download '.$downloadId.' price '.$priceId.'.');
    }

    public static function discountNotApplicable(string $code): self
    {
        return new self('discount_not_applicable', 'Discount '.$code.' does not apply to this item.');
    }

    public static function invalidQuantity(int $quantity): self
    {
        return new self('invalid_quantity', 'Invalid quantity '.$quantity.'.');
    }
}
