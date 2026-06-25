<?php
declare(strict_types=1);

use StockResource\Core\Plugin;

require_once dirname(__DIR__) . '/src/Plugin.php';

function assert_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

$entry = file_get_contents(dirname(__DIR__) . '/sr-core.php');
assert_true($entry !== false, 'plugin entry file is readable');
assert_true(str_contains($entry, 'Plugin Name: Stock Resource Core'), 'plugin header declares the core plugin name');
assert_true(str_contains($entry, 'Requires Plugins: easy-digital-downloads'), 'plugin header declares EDD dependency');
assert_true(str_contains($entry, 'Requires PHP: 8.3'), 'plugin header declares PHP requirement');

assert_same('sr-core', Plugin::slug(), 'plugin exposes stable slug');
assert_same('0.1.0', Plugin::version(), 'plugin exposes skeleton version');
assert_same(['easy-digital-downloads/easy-digital-downloads.php'], Plugin::requiredPlugins(), 'plugin declares runtime plugin dependency');
assert_same(['StockResource\\Platform\\BootstrapPlugin'], Plugin::requiredClasses(), 'plugin declares platform bootstrap dependency');
assert_same([], Plugin::missingRuntimeDependencies(
    pluginActive: static fn(string $plugin): bool => $plugin === 'easy-digital-downloads/easy-digital-downloads.php',
    classExists: static fn(string $class): bool => $class === 'StockResource\\Platform\\BootstrapPlugin',
), 'plugin reports no missing dependencies when EDD and bootstrap are available');
assert_same(['plugin:easy-digital-downloads/easy-digital-downloads.php', 'class:StockResource\\Platform\\BootstrapPlugin'], Plugin::missingRuntimeDependencies(
    pluginActive: static fn(string $plugin): bool => false,
    classExists: static fn(string $class): bool => false,
), 'plugin reports missing EDD and bootstrap without throwing');

echo "sr-core skeleton tests: ok\n";
