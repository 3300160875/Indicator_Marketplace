<?php
/**
 * Plugin Name: Stock Resource Private Downloads
 * Description: Private downloads plugin for the Stock Resource platform.
 * Version: 0.1.0
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Requires Plugins: easy-digital-downloads
 */
declare(strict_types=1);

use StockResource\PrivateDownloads\Plugin;

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

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'StockResource\\Contracts\\' => dirname(__DIR__) . '/sr-contracts/src/',
        'StockResource\\Entitlements\\' => dirname(__DIR__) . '/sr-entitlements/src/',
        'StockResource\\PrivateDownloads\\' => __DIR__ . '/src/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $path = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_readable($path)) {
            require_once $path;
        }
    }
});

if (class_exists(Plugin::class)) {
    Plugin::boot();
}
