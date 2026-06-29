<?php

declare(strict_types=1);

namespace StockResource\Core\Commerce;

use StockResource\Contracts\Value\Money;

final readonly class DiscountPolicy
{
    /**
     * @param  array<string, array{percent: int, applies_to: list<int>}>  $discounts
     */
    public function __construct(private array $discounts) {}

    public function discountAmount(?string $code, int $downloadId, Money $subtotal): Money
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return Money::fromString('0');
        }

        $discount = $this->discounts[$code] ?? null;
        if ($discount === null || ! in_array($downloadId, $discount['applies_to'], true)) {
            throw CommerceException::discountNotApplicable($code);
        }

        $cents = intdiv($this->toCents($subtotal) * max(0, min(100, $discount['percent'])), 100);

        return $this->fromCents($cents);
    }

    private function toCents(Money $money): int
    {
        $value = $money->toString();
        if (! str_contains($value, '.')) {
            return ((int) $value) * 100;
        }

        [$whole, $decimal] = explode('.', $value, 2);

        return ((int) $whole) * 100 + (int) substr(str_pad($decimal, 2, '0'), 0, 2);
    }

    private function fromCents(int $cents): Money
    {
        if ($cents === 0) {
            return Money::fromString('0');
        }

        $whole = intdiv($cents, 100);
        $decimal = $cents % 100;

        return Money::fromString(sprintf('%d.%02d', $whole, $decimal));
    }
}
