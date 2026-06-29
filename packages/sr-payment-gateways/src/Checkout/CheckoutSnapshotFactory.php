<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Checkout;

final readonly class CheckoutSnapshotFactory
{
    /**
     * @return array{user_id:int,order_amount:string,currency:string,amount_source:string,client_amount_ignored:string,terms_snapshot:array<string,string>,line_items:list<array<string,int|string>>,created_at:string}
     */
    public function fromRequest(CheckoutRequest $request): array
    {
        return [
            'user_id' => (int) $request->userId,
            'order_amount' => $request->serverTotal,
            'currency' => strtoupper($request->currency),
            'amount_source' => 'SERVER_RECALCULATED',
            'client_amount_ignored' => $request->clientTotal,
            'terms_snapshot' => $request->terms->snapshot(),
            'line_items' => $request->lineItems,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }
}
