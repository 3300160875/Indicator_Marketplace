<?php

declare(strict_types=1);

namespace StockResource\Core\Account\Orders;

final readonly class AccountOrderProjectionService
{
    public function __construct(private AccountOrderRepository $repository) {}

    public function cacheKey(int $userId, string $rulesVersion): string
    {
        $this->assertLoggedIn($userId);

        return 'account_orders:'.$userId.':'.trim($rulesVersion);
    }

    /**
     * @return array{state:string,cache_key:string,orders:list<array<string,mixed>>}
     */
    public function forUser(int $userId, string $rulesVersion): array
    {
        $this->assertLoggedIn($userId);

        $orders = array_map(
            fn (array $order): array => $this->projectOrder($order),
            $this->repository->forUser($userId),
        );

        return [
            'state' => $orders === [] ? 'empty' : 'ready',
            'cache_key' => $this->cacheKey($userId, $rulesVersion),
            'orders' => $orders,
        ];
    }

    private function assertLoggedIn(int $userId): void
    {
        if ($userId < 1) {
            throw AccountOrderAccessException::loginRequired();
        }
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function projectOrder(array $order): array
    {
        $items = is_array($order['items'] ?? null) ? array_values(array_filter($order['items'], 'is_array')) : [];
        $status = trim((string) ($order['status'] ?? 'pending'));

        return [
            'order_id' => (int) ($order['order_id'] ?? 0),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'total_amount' => trim((string) ($order['total_amount'] ?? '0')),
            'currency' => strtoupper(trim((string) ($order['currency'] ?? ''))),
            'created_at' => trim((string) ($order['created_at'] ?? '')),
            'completed_at' => trim((string) ($order['completed_at'] ?? '')),
            'items' => array_map(fn (array $item): array => $this->projectItem($item), $items),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function projectItem(array $item): array
    {
        $downloadStatus = trim((string) ($item['download_status'] ?? 'unavailable'));
        $quota = is_array($item['quota'] ?? null) ? $item['quota'] : [];
        $used = max(0, (int) ($quota['used'] ?? 0));
        $limit = max(0, (int) ($quota['limit'] ?? 0));

        return [
            'title' => trim((string) ($item['title'] ?? '')),
            'product_type' => trim((string) ($item['product_type'] ?? '')),
            'resource_id' => (int) ($item['resource_id'] ?? 0),
            'version_id' => (int) ($item['version_id'] ?? 0),
            'download_status' => $downloadStatus,
            'download_label' => $this->downloadLabel($downloadStatus),
            'quota_used' => $used,
            'quota_limit' => $limit,
            'quota_label' => $used.'/'.$limit,
            'quota_reset_at' => trim((string) ($quota['reset_at'] ?? '')),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'complete' => '已完成',
            'pending' => '待处理',
            'expired' => '已过期',
            'revoked' => '已撤权',
            'refunded' => '已退款',
            'partially_refunded' => '部分退款',
            default => '状态待确认',
        };
    }

    private function downloadLabel(string $downloadStatus): string
    {
        return match ($downloadStatus) {
            'available' => '可下载',
            'expired' => '已过期',
            'revoked' => '已撤权',
            'quota_reset' => '等待配额重置',
            default => '暂不可下载',
        };
    }
}
