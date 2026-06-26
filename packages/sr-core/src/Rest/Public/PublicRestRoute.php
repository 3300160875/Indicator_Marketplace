<?php
declare(strict_types=1);

namespace StockResource\Core\Rest\Public;

final readonly class PublicRestRoute
{
    /**
     * @param array<string, mixed> $arguments
     * @param callable(): bool $permissionCallback
     */
    public function __construct(
        private string $namespace,
        private string $method,
        private string $path,
        private string $operationId,
        private array $arguments,
        private mixed $permissionCallback,
    ) {
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function operationId(): string
    {
        return $this->operationId;
    }

    /**
     * @return array<string, mixed>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return callable(): bool
     */
    public function permissionCallback(): callable
    {
        return $this->permissionCallback;
    }
}
