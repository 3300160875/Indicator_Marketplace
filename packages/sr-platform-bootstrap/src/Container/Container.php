<?php
declare(strict_types=1);

namespace StockResource\Platform\Container;

use RuntimeException;

final class Container
{
    /** @var array<string, mixed> */
    private array $services = [];

    /** @var array<string, callable(self): mixed> */
    private array $factories = [];

    public function set(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
        unset($this->factories[$id]);
    }

    /**
     * @param callable(self): mixed $factory
     */
    public function factory(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->services[$id]);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services) || array_key_exists($id, $this->factories);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->services)) {
            return $this->services[$id];
        }

        if (array_key_exists($id, $this->factories)) {
            $this->services[$id] = $this->factories[$id]($this);
            unset($this->factories[$id]);

            return $this->services[$id];
        }

        throw new RuntimeException('Service is not registered: ' . $id);
    }
}
