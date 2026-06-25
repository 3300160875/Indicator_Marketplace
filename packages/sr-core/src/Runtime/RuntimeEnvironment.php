<?php
declare(strict_types=1);

namespace StockResource\Core\Runtime;

interface RuntimeEnvironment
{
    public function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void;

    public function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void;

    /**
     * @param array<string, mixed> $args
     */
    public function registerTaxonomy(string $taxonomy, string $objectType, array $args): void;

    public function taxonomyExists(string $taxonomy): bool;

    public function cliAvailable(): bool;

    public function addCliCommand(string $name, mixed $command): void;

    public function incomingHeader(string $name): ?string;

    public function sendHeader(string $name, string $value): void;
}
