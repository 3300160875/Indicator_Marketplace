<?php

declare(strict_types=1);

namespace StockResource\Core\Commerce;

final class OrderItemSnapshotFactory
{
    /**
     * @return array<string, mixed>
     */
    public static function fromValidation(ResourcePurchaseValidation $validation, string $rulesVersion): array
    {
        return [
            'product_type' => $validation->productType->value,
            'resource_id' => $validation->downloadId,
            'version_id' => (int) ($validation->resourceMeta['_sr_current_version_id'] ?? 0),
            'price_id' => $validation->priceId,
            'quantity' => $validation->quantity,
            'unit_amount' => $validation->unitAmount->toString(),
            'subtotal_amount' => $validation->subtotal->toString(),
            'discount_amount' => $validation->discountAmount->toString(),
            'total_amount' => $validation->total->toString(),
            'access_mode' => $validation->accessMode->value,
            'rules_version' => $rulesVersion,
            'rights_status' => (string) ($validation->resourceMeta['_sr_rights_status'] ?? 'pending'),
            'calculated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }
}
