<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Auth;

use InvalidArgumentException;

final readonly class AuthorizationService
{
    public function __construct(private RoleCapabilityMatrix $matrix) {}

    public function can(UserContext $user, string $capability, ?OwnedResourceSubject $subject = null): AuthorizationDecision
    {
        try {
            $definition = $this->matrix->capability($capability);
        } catch (InvalidArgumentException) {
            return AuthorizationDecision::deny('unknown_capability');
        }

        if ($definition->highRisk && ! $user->hasRole('administrator')) {
            return AuthorizationDecision::deny('high_risk_requires_administrator');
        }

        if (! $user->hasCapability($capability)) {
            return AuthorizationDecision::deny('missing_capability');
        }

        if (! $user->hasRole('administrator') && $definition->ownerRestricted) {
            if ($subject === null) {
                return AuthorizationDecision::deny('resource_subject_required');
            }

            if ($subject->ownerUserId !== $user->userId) {
                return AuthorizationDecision::deny('not_resource_owner');
            }
        }

        return AuthorizationDecision::allow();
    }
}
