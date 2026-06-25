<?php
declare(strict_types=1);

namespace StockResource\Core;

final class Plugin
{
    private const SLUG = 'sr-core';
    private const VERSION = '0.1.0';
    private const REQUIRED_PLUGINS = ['easy-digital-downloads/easy-digital-downloads.php'];
    private const REQUIRED_CLASSES = ['StockResource\\Platform\\BootstrapPlugin'];

    public static function slug(): string
    {
        return self::SLUG;
    }

    public static function version(): string
    {
        return self::VERSION;
    }

    /**
     * @return list<string>
     */
    public static function requiredPlugins(): array
    {
        return self::REQUIRED_PLUGINS;
    }

    /**
     * @return list<string>
     */
    public static function requiredClasses(): array
    {
        return self::REQUIRED_CLASSES;
    }

    /**
     * @param null|callable(string): bool $pluginActive
     * @param null|callable(string): bool $classExists
     * @return list<string>
     */
    public static function missingRuntimeDependencies(?callable $pluginActive = null, ?callable $classExists = null): array
    {
        $pluginActive ??= static fn(string $plugin): bool => ! function_exists('is_plugin_active') || is_plugin_active($plugin);
        $classExists ??= static fn(string $class): bool => class_exists($class);

        $missing = [];
        foreach (self::REQUIRED_PLUGINS as $plugin) {
            if (! $pluginActive($plugin)) {
                $missing[] = 'plugin:' . $plugin;
            }
        }
        foreach (self::REQUIRED_CLASSES as $class) {
            if (! $classExists($class)) {
                $missing[] = 'class:' . $class;
            }
        }

        return $missing;
    }

    public static function boot(): bool
    {
        return self::missingRuntimeDependencies() === [];
    }
}
