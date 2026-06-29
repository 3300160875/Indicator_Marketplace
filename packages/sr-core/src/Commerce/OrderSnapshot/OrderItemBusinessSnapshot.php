<?php

declare(strict_types=1);

namespace StockResource\Core\Commerce\OrderSnapshot;

use StockResource\Core\Integration\Edd\EddOrderItemSnapshot;
use StockResource\Core\Integration\Edd\EddOrderSnapshot;

final readonly class OrderItemBusinessSnapshot
{
    private const SNAPSHOT_VERSION = 1;

    /**
     * @param  array<string, string>  $termsSnapshot
     */
    public function __construct(
        public int $orderId,
        public int $orderItemId,
        public int $customerId,
        public int $userId,
        public int $downloadId,
        public int $priceId,
        public int $quantity,
        public string $currency,
        public string $unitAmount,
        public string $subtotalAmount,
        public string $discountAmount,
        public string $taxAmount,
        public string $totalAmount,
        public string $productType,
        public string $rulesVersion,
        public string $accessMode,
        public string $refundStatus,
        public ?int $resourceId,
        public ?int $versionId,
        public ?int $planDownloadId,
        public ?string $planCode,
        public array $termsSnapshot,
        public string $capturedAt,
        public string $idempotencyKey,
    ) {}

    public static function fromEdd(EddOrderSnapshot $order, EddOrderItemSnapshot $item): self
    {
        $business = $item->businessSnapshot;
        $productType = self::stringValue($business, 'product_type');
        $rulesVersion = self::stringValue($business, 'rules_version');
        if ($productType === '' || $rulesVersion === '') {
            throw OrderSnapshotException::invalidSnapshot('product_type and rules_version are required');
        }

        $termsSnapshot = $business['terms_snapshot'] ?? [];
        if (! is_array($termsSnapshot)) {
            throw OrderSnapshotException::invalidSnapshot('terms_snapshot is required');
        }
        $normalizedTermsSnapshot = self::normalizeTermsSnapshot($termsSnapshot);
        if ($normalizedTermsSnapshot === []) {
            throw OrderSnapshotException::invalidSnapshot('terms_snapshot is required');
        }

        $resourceId = self::intValue($business, 'resource_id');
        $versionId = self::intValue($business, 'version_id');
        $planDownloadId = self::intValue($business, 'plan_download_id');
        $planCode = self::nullableStringValue($business, 'plan_code');
        if ($productType === 'resource' && ($resourceId === null || $versionId === null)) {
            throw OrderSnapshotException::invalidSnapshot('resource_id and version_id are required for resource snapshots');
        }
        if ($productType === 'membership_plan' && ($planDownloadId === null || $planCode === null)) {
            throw OrderSnapshotException::invalidSnapshot('plan_download_id and plan_code are required for membership plan snapshots');
        }

        $payload = [
            'snapshot_version' => self::SNAPSHOT_VERSION,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'customer_id' => $order->customer->id,
            'user_id' => $order->customer->userId,
            'download_id' => $item->downloadId,
            'price_id' => self::intValue($business, 'price_id') ?? $item->priceId,
            'quantity' => $item->quantity,
            'currency' => strtoupper($order->currency),
            'unit_amount' => self::amountValue($business, 'unit_amount', $item->total->toString()),
            'subtotal_amount' => self::amountValue($business, 'subtotal_amount', $item->subtotal->toString()),
            'discount_amount' => self::amountValue($business, 'discount_amount', '0'),
            'tax_amount' => $item->tax->toString(),
            'total_amount' => self::amountValue($business, 'total_amount', $item->total->toString()),
            'product_type' => $productType,
            'rules_version' => $rulesVersion,
            'access_mode' => self::stringValue($business, 'access_mode'),
            'refund_status' => self::refundStatus($order->status),
            'resource_id' => $resourceId,
            'version_id' => $versionId,
            'plan_download_id' => $planDownloadId,
            'plan_code' => $planCode,
            'terms_snapshot' => $normalizedTermsSnapshot,
            'captured_at' => $order->completedAt !== '' ? $order->completedAt : $order->createdAt,
        ];

        return new self(
            orderId: $payload['order_id'],
            orderItemId: $payload['order_item_id'],
            customerId: $payload['customer_id'],
            userId: $payload['user_id'],
            downloadId: $payload['download_id'],
            priceId: $payload['price_id'],
            quantity: $payload['quantity'],
            currency: $payload['currency'],
            unitAmount: $payload['unit_amount'],
            subtotalAmount: $payload['subtotal_amount'],
            discountAmount: $payload['discount_amount'],
            taxAmount: $payload['tax_amount'],
            totalAmount: $payload['total_amount'],
            productType: $payload['product_type'],
            rulesVersion: $payload['rules_version'],
            accessMode: $payload['access_mode'],
            refundStatus: $payload['refund_status'],
            resourceId: $payload['resource_id'],
            versionId: $payload['version_id'],
            planDownloadId: $payload['plan_download_id'],
            planCode: $payload['plan_code'],
            termsSnapshot: $payload['terms_snapshot'],
            capturedAt: $payload['captured_at'],
            idempotencyKey: self::hashPayload($payload),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'snapshot_version' => self::SNAPSHOT_VERSION,
            'order_id' => $this->orderId,
            'order_item_id' => $this->orderItemId,
            'customer_id' => $this->customerId,
            'user_id' => $this->userId,
            'download_id' => $this->downloadId,
            'price_id' => $this->priceId,
            'quantity' => $this->quantity,
            'currency' => $this->currency,
            'unit_amount' => $this->unitAmount,
            'subtotal_amount' => $this->subtotalAmount,
            'discount_amount' => $this->discountAmount,
            'tax_amount' => $this->taxAmount,
            'total_amount' => $this->totalAmount,
            'product_type' => $this->productType,
            'rules_version' => $this->rulesVersion,
            'access_mode' => $this->accessMode,
            'refund_status' => $this->refundStatus,
            'resource_id' => $this->resourceId,
            'version_id' => $this->versionId,
            'plan_download_id' => $this->planDownloadId,
            'plan_code' => $this->planCode,
            'terms_snapshot' => $this->termsSnapshot,
            'captured_at' => $this->capturedAt,
            'idempotency_key' => $this->idempotencyKey,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function hashPayload(array $payload): string
    {
        self::sortRecursive($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function sortRecursive(array &$payload): void
    {
        ksort($payload);
        foreach ($payload as &$value) {
            if (is_array($value)) {
                self::sortRecursive($value);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function stringValue(array $snapshot, string $key): string
    {
        return trim((string) ($snapshot[$key] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function nullableStringValue(array $snapshot, string $key): ?string
    {
        $value = self::stringValue($snapshot, $key);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function intValue(array $snapshot, string $key): ?int
    {
        if (! array_key_exists($key, $snapshot) || $snapshot[$key] === null || $snapshot[$key] === '') {
            return null;
        }

        return (int) $snapshot[$key];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function amountValue(array $snapshot, string $key, string $fallback): string
    {
        $amount = trim((string) ($snapshot[$key] ?? $fallback));

        return str_starts_with($amount, '-') ? substr($amount, 1) : $amount;
    }

    /**
     * @param  array<string, mixed>  $termsSnapshot
     * @return array<string, string>
     */
    private static function normalizeTermsSnapshot(array $termsSnapshot): array
    {
        $normalized = [];
        foreach ($termsSnapshot as $key => $value) {
            if (is_string($key) && (is_string($value) || is_numeric($value))) {
                $normalized[$key] = (string) $value;
            }
        }
        ksort($normalized);

        return $normalized;
    }

    private static function refundStatus(string $orderStatus): string
    {
        return match ($orderStatus) {
            'partially_refunded' => 'partial',
            'refunded' => 'full',
            default => 'none',
        };
    }
}
