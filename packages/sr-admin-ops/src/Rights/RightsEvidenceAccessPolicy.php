<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Rights;

use StockResource\AdminOps\Auth\AuthorizationDecision;
use StockResource\AdminOps\Auth\AuthorizationService;
use StockResource\AdminOps\Auth\OwnedResourceSubject;
use StockResource\AdminOps\Auth\UserContext;

final readonly class RightsEvidenceAccessPolicy
{
    private const CONTRACT_CAPABILITY = 'view_sr_rights_evidence';
    private const CURRENT_MATRIX_CAPABILITY = 'sr_review_rights_evidence';

    public function __construct(private AuthorizationService $authorization)
    {
    }

    public function canViewEvidence(UserContext $user, RightsRecord $record, int $resourceOwnerUserId): AuthorizationDecision
    {
        if ($record->evidenceStorageKey === null) {
            return AuthorizationDecision::deny('evidence_missing');
        }

        if ($user->hasCapability(self::CONTRACT_CAPABILITY)) {
            if (! $user->hasRole('administrator') && $resourceOwnerUserId !== $user->userId) {
                return AuthorizationDecision::deny('not_resource_owner');
            }

            return AuthorizationDecision::allow();
        }

        $decision = $this->authorization->can(
            $user,
            self::CURRENT_MATRIX_CAPABILITY,
            new OwnedResourceSubject($record->resourceId, $resourceOwnerUserId),
        );

        if ($decision->allowed) {
            return $decision;
        }

        return $user->hasCapability(self::CURRENT_MATRIX_CAPABILITY)
            ? $decision
            : AuthorizationDecision::deny('missing_rights_evidence_capability');
    }

    /**
     * @return list<string>
     */
    public function capabilitySlugs(): array
    {
        return [self::CONTRACT_CAPABILITY, self::CURRENT_MATRIX_CAPABILITY];
    }
}
