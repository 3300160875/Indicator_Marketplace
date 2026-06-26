<?php

declare(strict_types=1);

namespace StockResource\Core\Integration\Edd;

final class EddCompatibilityFixtures
{
    /**
     * @return array<string, mixed>
     */
    public static function edd369(): array
    {
        return [
            'version' => '3.6.9',
            'customers' => [
                20 => [
                    'id' => 20,
                    'user_id' => 200,
                    'email' => 'buyer@example.test',
                    'name' => 'Buyer One',
                ],
                30 => [
                    'id' => 30,
                    'user_id' => 300,
                    'email' => 'partial@example.test',
                    'name' => 'Partial Buyer',
                ],
            ],
            'orders' => [
                1 => [
                    'id' => 1,
                    'type' => 'sale',
                    'status' => 'complete',
                    'customer_id' => 20,
                    'subtotal' => '12.340000000',
                    'tax' => '0.000000000',
                    'total' => '12.340000000',
                    'currency' => 'CNY',
                    'date_created' => '2026-06-25T06:30:00Z',
                    'date_completed' => '2026-06-25T06:34:30Z',
                ],
                2 => [
                    'id' => 2,
                    'type' => 'refund',
                    'status' => 'complete',
                    'parent' => 1,
                    'customer_id' => 20,
                    'subtotal' => '-12.340000000',
                    'tax' => '0.000000000',
                    'total' => '-12.340000000',
                    'currency' => 'CNY',
                    'date_created' => '2026-06-25T06:40:00Z',
                    'date_completed' => '2026-06-25T06:40:00Z',
                ],
                3 => [
                    'id' => 3,
                    'type' => 'sale',
                    'status' => 'partially_refunded',
                    'customer_id' => 30,
                    'subtotal' => '8.000000000',
                    'tax' => '0.000000000',
                    'total' => '8.000000000',
                    'currency' => 'CNY',
                    'date_created' => '2026-06-25T06:41:00Z',
                    'date_completed' => '2026-06-25T06:42:00Z',
                ],
                4 => [
                    'id' => 4,
                    'type' => 'refund',
                    'status' => 'complete',
                    'parent' => 3,
                    'customer_id' => 30,
                    'subtotal' => '-3.000000000',
                    'tax' => '0.000000000',
                    'total' => '-3.000000000',
                    'currency' => 'CNY',
                    'date_created' => '2026-06-25T06:45:00Z',
                    'date_completed' => '2026-06-25T06:45:00Z',
                ],
            ],
            'items' => [
                1 => [
                    [
                        'id' => 101,
                        'order_id' => 1,
                        'product_id' => 10,
                        'price_id' => 0,
                        'quantity' => 1,
                        'subtotal' => '12.340000000',
                        'tax' => '0.000000000',
                        'total' => '12.340000000',
                        'snapshot' => [
                            'product_type' => 'resource',
                            'resource_id' => 1001,
                            'access_mode' => 'purchase',
                            'rules_version' => 'v1',
                        ],
                    ],
                ],
                2 => [
                    [
                        'id' => 201,
                        'order_id' => 2,
                        'product_id' => 10,
                        'price_id' => 0,
                        'quantity' => -1,
                        'subtotal' => '-12.340000000',
                        'tax' => '0.000000000',
                        'total' => '-12.340000000',
                        'snapshot' => [
                            'product_type' => 'resource',
                            'resource_id' => 1001,
                            'access_mode' => 'purchase',
                            'rules_version' => 'v1',
                        ],
                    ],
                ],
                3 => [
                    [
                        'id' => 301,
                        'order_id' => 3,
                        'product_id' => 11,
                        'price_id' => 0,
                        'quantity' => 1,
                        'subtotal' => '8.000000000',
                        'tax' => '0.000000000',
                        'total' => '8.000000000',
                        'snapshot' => [
                            'product_type' => 'resource',
                            'resource_id' => 1002,
                            'access_mode' => 'purchase',
                            'rules_version' => 'v1',
                        ],
                    ],
                ],
                4 => [
                    [
                        'id' => 401,
                        'order_id' => 4,
                        'product_id' => 11,
                        'price_id' => 0,
                        'quantity' => -1,
                        'subtotal' => '-3.000000000',
                        'tax' => '0.000000000',
                        'total' => '-3.000000000',
                        'snapshot' => [
                            'product_type' => 'resource',
                            'resource_id' => 1002,
                            'access_mode' => 'purchase',
                            'rules_version' => 'v1',
                        ],
                    ],
                ],
            ],
            'hooks' => [
                'complete' => [
                    'edd_update_payment_status',
                    'edd_pre_complete_purchase',
                    'edd_complete_download_purchase',
                    'edd_complete_purchase',
                    'edd_after_payment_actions',
                    'edd_after_order_actions',
                ],
                'refund' => ['edd_refund_order'],
            ],
            'api_touchpoints' => [
                'EDD\\Orders\\Order',
                'edd_get_order',
                'edd_get_order_items',
                'edd_get_customer',
            ],
        ];
    }
}
