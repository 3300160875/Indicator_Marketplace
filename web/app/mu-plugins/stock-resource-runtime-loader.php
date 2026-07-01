<?php
/**
 * Plugin Name: Stock Resource Runtime Loader
 * Description: Loads first-party Stock Resource packages in the local Bedrock runtime.
 * Version: 0.1.0
 * Requires PHP: 8.3
 */
declare(strict_types=1);

$sr_root = dirname(__DIR__, 3);

spl_autoload_register(static function (string $class) use ($sr_root): void {
    $prefixes = [
        'StockResource\\AdminOps\\' => $sr_root . '/packages/sr-admin-ops/src/',
        'StockResource\\Contracts\\' => $sr_root . '/packages/sr-contracts/src/',
        'StockResource\\Core\\' => $sr_root . '/packages/sr-core/src/',
        'StockResource\\Entitlements\\' => $sr_root . '/packages/sr-entitlements/src/',
        'StockResource\\PaymentGateways\\' => $sr_root . '/packages/sr-payment-gateways/src/',
        'StockResource\\Platform\\' => $sr_root . '/packages/sr-platform-bootstrap/src/',
        'StockResource\\PrivateDownloads\\' => $sr_root . '/packages/sr-private-downloads/src/',
    ];

    foreach ($prefixes as $prefix => $base_dir) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $relative_class = substr($class, strlen($prefix));
        $path = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (is_readable($path)) {
            require_once $path;
        }
    }
});

foreach ([
    $sr_root . '/packages/sr-platform-bootstrap/sr-platform-bootstrap.php',
    $sr_root . '/packages/sr-core/sr-core.php',
    $sr_root . '/packages/sr-entitlements/sr-entitlements.php',
    $sr_root . '/packages/sr-private-downloads/sr-private-downloads.php',
] as $entry) {
    if (is_readable($entry)) {
        require_once $entry;
    }
}
