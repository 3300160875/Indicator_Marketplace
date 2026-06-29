<?php

declare(strict_types=1);

namespace StockResource\Core\Commerce;

use StockResource\Contracts\Value\Money;

final readonly class ResourcePurchaseValidator
{
    public function __construct(
        private PriceBook $priceBook,
        private DiscountPolicy $discountPolicy,
    ) {}

    public function validate(ResourcePurchaseRequest $request): ResourcePurchaseValidation
    {
        if ($request->quantity < 1) {
            throw CommerceException::invalidQuantity($request->quantity);
        }

        $metaProductType = (string) ($request->resourceMeta['_sr_product_type'] ?? 'resource');
        if ($metaProductType !== $request->productType->value) {
            throw CommerceException::productTypeMismatch($request->productType->value, $metaProductType);
        }

        $metaAccessMode = (string) ($request->resourceMeta['_sr_access_mode'] ?? AccessMode::Unavailable->value);
        if ($metaAccessMode !== $request->accessMode->value) {
            throw CommerceException::accessModeMismatch($request->accessMode->value, $metaAccessMode);
        }

        if (! $request->accessMode->producesPayableOrder()) {
            return new ResourcePurchaseValidation(
                downloadId: $request->downloadId,
                productType: $request->productType,
                accessMode: $request->accessMode,
                priceId: $request->priceId,
                quantity: $request->quantity,
                unitAmount: Money::fromString('0'),
                subtotal: Money::fromString('0'),
                discountAmount: Money::fromString('0'),
                total: Money::fromString('0'),
                purchasable: false,
                priceSource: 'SERVER_RECALCULATED',
                resourceMeta: $request->resourceMeta,
            );
        }

        $quote = $this->priceBook->quote($request->downloadId, $request->priceId);
        $subtotal = $this->multiply($quote->unitAmount, $request->quantity);
        $discount = $this->discountPolicy->discountAmount($request->discountCode, $request->downloadId, $subtotal);
        $total = $this->fromCents(max(0, $this->toCents($subtotal) - $this->toCents($discount)));

        return new ResourcePurchaseValidation(
            downloadId: $request->downloadId,
            productType: $request->productType,
            accessMode: $request->accessMode,
            priceId: $request->priceId,
            quantity: $request->quantity,
            unitAmount: $quote->unitAmount,
            subtotal: $subtotal,
            discountAmount: $discount,
            total: $total,
            purchasable: true,
            priceSource: $quote->source,
            resourceMeta: $request->resourceMeta,
        );
    }

    private function multiply(Money $money, int $quantity): Money
    {
        return $this->fromCents($this->toCents($money) * $quantity);
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

        return Money::fromString(sprintf('%d.%02d', intdiv($cents, 100), $cents % 100));
    }
}
