<?php
declare(strict_types=1);

namespace StockResource\Platform\Runtime;

interface Runtime
{
    public function phpVersion(): string;

    public function wordpressVersion(): ?string;

    public function pluginVersion(string $pluginFile): ?string;

    public function option(string $name, mixed $default = null): mixed;

    public function isAdmin(): bool;

    public function addAction(string $hook, callable $callback): void;

    public function adminNotice(string $type, string $message): void;
}
