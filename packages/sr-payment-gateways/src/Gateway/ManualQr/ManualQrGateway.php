<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Gateway\ManualQr;

final readonly class ManualQrGateway
{
    public function __construct(private ManualQrGatewayConfig $config) {}

    /**
     * @return array<string, int|string>
     */
    public function createIntent(ManualQrPaymentRequest $request): array
    {
        if (! $this->config->manualPaymentEnabled) {
            throw ManualQrException::paymentDisabled();
        }

        return [
            'gateway_id' => 'manual_qr',
            'order_id' => $request->orderId,
            'user_id' => $request->userId,
            'amount' => $request->amount,
            'currency' => strtoupper($request->currency),
            'channel' => $this->config->qrChannel,
            'account_label' => $this->config->accountLabel,
            'instructions_version' => $this->config->instructionsVersion,
            'edd_order_status' => 'pending',
            'idempotency_key' => $request->idempotencyKey,
            'notice' => '请提交付款凭证等待人工核验；本页面不会自动到账，也不会自动完成订单。',
        ];
    }
}
