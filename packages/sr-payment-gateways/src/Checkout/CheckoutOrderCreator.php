<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Checkout;

final readonly class CheckoutOrderCreator
{
    public function __construct(
        private CheckoutPolicy $policy,
        private CheckoutSnapshotFactory $snapshotFactory,
    ) {}

    /**
     * @param  callable(array<string,mixed>): array<string,mixed>  $createOrder
     * @return array<string,mixed>
     */
    public function create(CheckoutRequest $request, callable $createOrder): array
    {
        $this->policy->assertCanCreateOrder($request);

        return $createOrder($this->snapshotFactory->fromRequest($request));
    }
}
