<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Auth;

final readonly class UserContext
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public int $userId,
        public array $roles,
        public array $capabilities,
    ) {}

    /**
     * @param  list<string>  $roles
     */
    public static function fromRoles(int $userId, array $roles, RoleCapabilityMatrix $matrix): self
    {
        $capabilities = [];
        foreach ($roles as $roleSlug) {
            foreach ($matrix->role($roleSlug)->capabilities as $capability) {
                $capabilities[$capability] = $capability;
            }
        }

        return new self($userId, array_values($roles), array_values($capabilities));
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }
}
