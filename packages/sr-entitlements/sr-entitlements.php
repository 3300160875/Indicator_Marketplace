<?php
/**
 * Plugin Name: Stock Resource Entitlements
 * Description: Entitlements plugin for the Stock Resource platform.
 * Version: 0.1.0
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Requires Plugins: easy-digital-downloads
 */
declare(strict_types=1);

use StockResource\Entitlements\Plugin;

$autoloads = [
    __DIR__ . '/vendor/autoload.php',
    dirname(__DIR__, 3) . '/vendor/autoload.php',
];

foreach ($autoloads as $autoload) {
    if (is_readable($autoload)) {
        require_once $autoload;
        break;
    }
}

if (class_exists(Plugin::class)) {
    Plugin::boot();
}
