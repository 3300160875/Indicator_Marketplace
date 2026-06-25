<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$phase = $argv[1] ?? 'install';

function sr006_run_child(string $phase): int
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($phase);
    passthru($cmd, $code);
    return (int) $code;
}

function sr006_describe(mixed $value): mixed
{
    if (is_object($value)) {
        $out = ['class' => get_class($value)];
        foreach (['id', 'ID', 'status', 'type', 'parent', 'total', 'email'] as $key) {
            if (@isset($value->{$key})) {
                $out[$key] = @$value->{$key};
            }
        }
        return $out;
    }

    if (is_array($value)) {
        return array_slice($value, 0, 8, true);
    }

    return $value;
}

function sr006_ensure_edd_component_tables(): void
{
    global $wpdb;

    if (function_exists('edd_setup_components')) {
        edd_setup_components();
    }

    if (function_exists('edd_register_component') && ! edd_get_component('session')) {
        edd_register_component('session', [
            'schema' => '\\EDD\\Database\\Schemas\\Sessions',
            'table' => '\\EDD\\Database\\Tables\\Sessions',
            'query' => '\\EDD\\Database\\Queries\\Session',
            'object' => '\\EDD\\Sessions\\Session',
            'meta' => false,
        ]);
    }

    if (! function_exists('edd_install_component_database_tables')) {
        throw new RuntimeException('edd_install_component_database_tables unavailable');
    }

    edd_install_component_database_tables();

    $requiredTables = [
        'edd_customers',
        'edd_orders',
        'edd_order_items',
        'edd_logs',
        'edd_sessions',
    ];
    $missing = [];

    foreach ($requiredTables as $suffix) {
        $table = $wpdb->prefix . $suffix;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($found !== $table) {
            $missing[] = $table;
        }
    }

    if ([] !== $missing) {
        throw new RuntimeException('edd_tables_missing=' . implode(',', $missing));
    }

    echo "edd_tables=ready\n";
}

function sr006_remove_hook_method(string $hook, string $class, string $method): int
{
    global $wp_filter;

    if (! isset($wp_filter[$hook]) || ! $wp_filter[$hook] instanceof WP_Hook) {
        return 0;
    }

    $removed = 0;
    foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            if (
                is_array($callback['function'])
                && is_object($callback['function'][0])
                && is_a($callback['function'][0], $class)
                && $callback['function'][1] === $method
            ) {
                remove_action($hook, [$callback['function'][0], $method], (int) $priority);
                $removed++;
            }
        }
    }

    return $removed;
}

if ('install' === $phase) {
    define('WP_INSTALLING', true);
    require $root . '/web/wp/wp-load.php';
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    if (! is_blog_installed()) {
        wp_install(
            'SR-006 Runtime Spike',
            'sr006-admin',
            'sr006-admin@example.test',
            false,
            '',
            'sr006-password'
        );
        echo "wordpress_installed=yes\n";
    } else {
        echo "wordpress_installed=already\n";
    }

    exit(sr006_run_child('activate'));
}

if ('activate' === $phase) {
    require $root . '/web/wp/wp-load.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    $plugin = 'easy-digital-downloads/easy-digital-downloads.php';
    if (! is_plugin_active($plugin)) {
        $result = activate_plugin($plugin);
        if (is_wp_error($result)) {
            fwrite(STDERR, 'edd_activate_error=' . $result->get_error_message() . "\n");
            exit(1);
        }
        echo "edd_activated=yes\n";
        sr006_ensure_edd_component_tables();
        exit(sr006_run_child('run'));
    }

    echo "edd_activated=already\n";
    sr006_ensure_edd_component_tables();
    exit(sr006_run_child('run'));
}

if ('run' !== $phase) {
    fwrite(STDERR, "unknown_phase={$phase}\n");
    exit(2);
}

require $root . '/web/wp/wp-load.php';

if (! function_exists('edd_add_order')) {
    fwrite(STDERR, "edd_loaded=no\n");
    exit(1);
}

wp_set_current_user(1);
update_option('edd_settings', array_merge((array) get_option('edd_settings', []), [
    'test_mode' => '1',
    'currency' => 'USD',
]));

$events = [];
$watch = static function (string $hook, int $acceptedArgs = 1) use (&$events): void {
    add_action($hook, static function (...$args) use (&$events, $hook): void {
        $events[] = [
            'hook' => $hook,
            'args' => array_map('sr006_describe', $args),
        ];
    }, 1, $acceptedArgs);
};

foreach ([
    'edd_update_payment_status' => 3,
    'edd_pre_complete_purchase' => 1,
    'edd_complete_download_purchase' => 5,
    'edd_complete_purchase' => 3,
    'edd_after_payment_actions' => 3,
    'edd_after_order_actions' => 3,
    'edd_refund_order' => 3,
] as $hook => $acceptedArgs) {
    $watch($hook, $acceptedArgs);
}

add_filter('edd_use_after_payment_actions', '__return_false');
add_filter('edd_is_order_refundable_by_override', '__return_true');

$emailHooksRemoved = [
    'order_emails' => sr006_remove_hook_method('edd_after_order_actions', 'EDD\\Emails\\Triggers', 'send_order_emails'),
    'refund_emails' => sr006_remove_hook_method('edd_refund_order', 'EDD\\Emails\\Triggers', 'send_refund_receipt'),
];

function sr006_create_order(string $label, float $amount): array
{
    $productId = wp_insert_post([
        'post_type' => 'download',
        'post_status' => 'publish',
        'post_title' => $label,
    ], true);

    if (is_wp_error($productId)) {
        throw new RuntimeException($productId->get_error_message());
    }

    update_post_meta((int) $productId, 'edd_price', (string) $amount);

    $email = strtolower(str_replace(' ', '-', $label)) . '@example.test';
    $customerId = edd_add_customer([
        'email' => $email,
        'name' => $label . ' Customer',
        'user_id' => 0,
    ]);

    if (! $customerId) {
        throw new RuntimeException('edd_add_customer failed');
    }

    $orderId = edd_add_order([
        'status' => 'pending',
        'type' => 'sale',
        'user_id' => 0,
        'customer_id' => $customerId,
        'email' => $email,
        'ip' => '127.0.0.1',
        'gateway' => 'manual',
        'mode' => 'test',
        'currency' => 'USD',
        'payment_key' => md5($label . microtime(true)),
        'subtotal' => $amount,
        'discount' => 0,
        'tax' => 0,
        'total' => $amount,
        'date_refundable' => gmdate('Y-m-d H:i:s', time() + 86400),
    ]);

    if (! $orderId) {
        throw new RuntimeException('edd_add_order failed');
    }

    edd_update_order_meta((int) $orderId, '_edd_should_send_order_receipt', false);
    edd_update_order_meta((int) $orderId, '_edd_should_send_admin_order_notice', false);

    $itemId = edd_add_order_item([
        'order_id' => $orderId,
        'product_id' => (int) $productId,
        'product_name' => $label,
        'cart_index' => 0,
        'type' => 'download',
        'status' => 'pending',
        'quantity' => 1,
        'amount' => $amount,
        'subtotal' => $amount,
        'discount' => 0,
        'tax' => 0,
        'total' => $amount,
    ]);

    if (! $itemId) {
        throw new RuntimeException('edd_add_order_item failed');
    }

    return [
        'product_id' => (int) $productId,
        'customer_id' => (int) $customerId,
        'order_id' => (int) $orderId,
        'item_id' => (int) $itemId,
    ];
}

$full = sr006_create_order('SR006 Full Refund', 12.34);
$completed = edd_update_order_status($full['order_id'], 'complete');
$duplicateComplete = edd_update_order_status($full['order_id'], 'complete');
$fullRefund = edd_refund_order($full['order_id']);
if (is_wp_error($fullRefund)) {
    throw new RuntimeException('full_refund_error=' . $fullRefund->get_error_message());
}

$partial = sr006_create_order('SR006 Partial Refund', 8.00);
edd_update_order_status($partial['order_id'], 'complete');
$partialRefund = edd_refund_order($partial['order_id'], [[
    'order_item_id' => $partial['item_id'],
    'quantity' => 1,
    'subtotal' => 3.00,
    'tax' => 0.00,
]], []);
if (is_wp_error($partialRefund)) {
    throw new RuntimeException('partial_refund_error=' . $partialRefund->get_error_message());
}

$fullRefundItems = edd_get_order_items(['order_id' => (int) $fullRefund, 'number' => 20]);
$partialRefundItems = edd_get_order_items(['order_id' => (int) $partialRefund, 'number' => 20]);

echo json_encode([
    'wp_version' => get_bloginfo('version'),
    'edd_version' => defined('EDD_VERSION') ? EDD_VERSION : null,
    'complete_first_result' => (bool) $completed,
    'complete_duplicate_result' => (bool) $duplicateComplete,
    'full_order' => $full,
    'full_refund_id' => (int) $fullRefund,
    'full_refund_items' => array_map('sr006_describe', $fullRefundItems),
    'partial_order' => $partial,
    'partial_refund_id' => (int) $partialRefund,
    'partial_refund_items' => array_map('sr006_describe', $partialRefundItems),
    'email_hooks_removed' => $emailHooksRemoved,
    'events' => $events,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
