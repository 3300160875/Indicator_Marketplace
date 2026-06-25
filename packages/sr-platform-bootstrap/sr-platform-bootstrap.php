<?php
/**
 * Plugin Name: Stock Resource Platform Bootstrap
 * Description: MU plugin bootstrap for the Stock Resource platform.
 * Version: 0.1.0
 * Requires PHP: 8.3
 */
declare(strict_types=1);

use StockResource\Platform\BootstrapPlugin;
use StockResource\Platform\Dependency\DependencyChecker;
use StockResource\Platform\Provider\PlatformServiceProvider;
use StockResource\Platform\Runtime\WordPressRuntime;

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

if (class_exists(BootstrapPlugin::class)) {
    (new BootstrapPlugin(
        new WordPressRuntime(),
        DependencyChecker::platformDefaults(),
        [new PlatformServiceProvider()],
    ))->boot();
}
