<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Auth;

final readonly class RoleDefinition
{
    /**
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public string $slug,
        public string $label,
        public array $capabilities,
    ) {}
}
