<?php
declare(strict_types=1);

namespace StockResource\Platform\Runtime;

final class WordPressRuntime implements Runtime
{
    public function phpVersion(): string
    {
        return PHP_VERSION;
    }

    public function wordpressVersion(): ?string
    {
        if (! function_exists('get_bloginfo')) {
            return null;
        }

        return (string) get_bloginfo('version');
    }

    public function pluginVersion(string $pluginFile): ?string
    {
        if (! function_exists('get_plugins')) {
            $pluginApi = defined('ABSPATH') ? ABSPATH . 'wp-admin/includes/plugin.php' : null;
            if ($pluginApi !== null && is_readable($pluginApi)) {
                require_once $pluginApi;
            }
        }

        if (! function_exists('get_plugins')) {
            return null;
        }

        $plugins = get_plugins();

        return isset($plugins[$pluginFile]['Version']) ? (string) $plugins[$pluginFile]['Version'] : null;
    }

    public function option(string $name, mixed $default = null): mixed
    {
        if (! function_exists('get_option')) {
            return $default;
        }

        return get_option($name, $default);
    }

    public function isAdmin(): bool
    {
        return function_exists('is_admin') && is_admin();
    }

    public function addAction(string $hook, callable $callback): void
    {
        if (function_exists('add_action')) {
            add_action($hook, $callback);
        }
    }

    public function adminNotice(string $type, string $message): void
    {
        $safeType = preg_replace('/[^a-z0-9_-]/i', '', $type) ?: 'error';
        $escapedMessage = function_exists('esc_html')
            ? esc_html($message)
            : htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo '<div class="notice notice-' . $safeType . '"><p>' . $escapedMessage . '</p></div>';
    }
}
