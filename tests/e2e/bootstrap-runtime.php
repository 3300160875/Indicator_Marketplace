<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

function sr066_run_child(string $phase): int
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($phase);
    passthru($cmd, $code);

    return (int) $code;
}

function sr066_ensure_edd_component_tables(): void
{
    global $wpdb;

    if (function_exists('edd_setup_components')) {
        edd_setup_components();
    }

    if (function_exists('edd_register_component') && function_exists('edd_get_component') && ! edd_get_component('session')) {
        edd_register_component('session', [
            'schema' => '\\EDD\\Database\\Schemas\\Sessions',
            'table' => '\\EDD\\Database\\Tables\\Sessions',
            'query' => '\\EDD\\Database\\Queries\\Session',
            'object' => '\\EDD\\Sessions\\Session',
            'meta' => false,
        ]);
    }

    if (! function_exists('edd_install_component_database_tables')) {
        throw new RuntimeException('edd_install_component_database_tables unavailable.');
    }

    edd_install_component_database_tables();

    $required = ['edd_customers', 'edd_orders', 'edd_order_items', 'edd_logs', 'edd_sessions'];
    $missing = [];
    foreach ($required as $suffix) {
        $table = $wpdb->prefix . $suffix;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($found !== $table) {
            $missing[] = $table;
        }
    }

    if ($missing !== []) {
        throw new RuntimeException('EDD tables missing: ' . implode(', ', $missing));
    }
}

$phase = $argv[1] ?? 'install';

if ($phase === 'install') {
    putenv('SR_E2E_ENABLED=1');
    define('WP_INSTALLING', true);
    require $root . '/web/wp/wp-load.php';
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    if (! is_blog_installed()) {
        wp_install(
            'Indicator Marketplace E2E',
            'sr066-admin',
            'sr066-admin@example.test',
            false,
            '',
            'sr066-password'
        );
        echo "wordpress_installed=yes\n";
    } else {
        echo "wordpress_installed=already\n";
    }

    exit(sr066_run_child('activate'));
}

if ($phase === 'activate') {
    putenv('SR_E2E_ENABLED=1');
    require $root . '/web/wp/wp-load.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/theme.php';

    $plugin = 'easy-digital-downloads/easy-digital-downloads.php';
    if (! is_plugin_active($plugin)) {
        $result = activate_plugin($plugin);
        if (is_wp_error($result)) {
            fwrite(STDERR, 'edd_activate_error=' . $result->get_error_message() . "\n");
            exit(1);
        }
        echo "edd_activated=yes\n";
    } else {
        echo "edd_activated=already\n";
    }

    sr066_ensure_edd_component_tables();
    update_option('edd_settings', array_merge((array) get_option('edd_settings', []), [
        'test_mode' => '1',
        'currency' => 'USD',
    ]));

    if (wp_get_theme('stock-resource-theme')->exists()) {
        switch_theme('stock-resource-theme');
        echo "theme=stock-resource-theme\n";
    }

    global $wpdb;
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('sr_e2e_p0_run_') . '%',
    ));
    echo "e2e_runtime=ready\n";
    exit(0);
}

fwrite(STDERR, "unknown_phase={$phase}\n");
exit(2);
