<?php
declare(strict_types=1);

namespace StockResource\Core\Runtime;

final class WordPressRuntimeEnvironment implements RuntimeEnvironment
{
    public function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        if (function_exists('add_action')) {
            add_action($hook, $callback, $priority, $acceptedArgs);
        }
    }

    public function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        if (function_exists('add_filter')) {
            add_filter($hook, $callback, $priority, $acceptedArgs);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function registerTaxonomy(string $taxonomy, string $objectType, array $args): void
    {
        if (function_exists('register_taxonomy')) {
            register_taxonomy($taxonomy, $objectType, $args);
        }
    }

    public function taxonomyExists(string $taxonomy): bool
    {
        return function_exists('taxonomy_exists') && taxonomy_exists($taxonomy);
    }

    public function cliAvailable(): bool
    {
        return defined('WP_CLI') && WP_CLI && class_exists('WP_CLI');
    }

    public function addCliCommand(string $name, mixed $command): void
    {
        if ($this->cliAvailable()) {
            \WP_CLI::add_command($name, $command);
        }
    }

    public function incomingHeader(string $name): ?string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$serverKey] ?? null;

        return is_string($value) ? $value : null;
    }

    public function sendHeader(string $name, string $value): void
    {
        if (! headers_sent()) {
            header($name . ': ' . $value);
        }
    }
}
