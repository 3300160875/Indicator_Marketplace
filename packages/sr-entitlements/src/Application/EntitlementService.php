<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Application;

use DateTimeImmutable;
use DateTimeZone;
use StockResource\Contracts\Entitlement\AccessDecision;
use StockResource\Contracts\Entitlement\AccessDecisionContext;
use StockResource\Entitlements\Infrastructure\Repository\Entitlement;
use StockResource\Entitlements\Infrastructure\Repository\EntitlementRepository;

final readonly class EntitlementService
{
    private mixed $quotaResolver;

    public function __construct(
        private EntitlementRepository $repository,
        mixed $quotaResolver = null,
    ) {
        $this->quotaResolver = $quotaResolver;
    }

    public function decide(AccessDecisionContext $context): AccessDecision
    {
        if ($context->resourceStatus !== 'published' || $context->accessMode === 'unavailable') {
            return AccessDecision::deny('resource_unavailable');
        }

        if ($context->accessMode === 'free') {
            return AccessDecision::allow('free_resource', 'FREE');
        }

        if ($context->userId === null) {
            return AccessDecision::deny('login_required');
        }

        $entitlements = array_values(array_filter(
            $this->repository->forUser($context->userId),
            static fn (Entitlement $entitlement): bool => $entitlement->isActive($context->atUtc),
        ));

        if (in_array($context->accessMode, ['purchase', 'purchase_or_vip'], true)) {
            $purchase = $this->decisionForSource(
                entitlements: $entitlements,
                context: $context,
                grantTypes: ['purchase', 'resource'],
                source: 'PURCHASE',
                reasonCode: 'single_purchase',
            );

            if ($purchase !== null) {
                return $purchase;
            }
        }

        $manual = $this->decisionForSource(
            entitlements: $entitlements,
            context: $context,
            grantTypes: ['manual'],
            source: 'MANUAL',
            reasonCode: 'manual_grant',
        );
        if ($manual !== null) {
            return $manual;
        }

        if (in_array($context->accessMode, ['vip', 'purchase_or_vip'], true)) {
            $vip = $this->decisionForSource(
                entitlements: $entitlements,
                context: $context,
                grantTypes: ['membership', 'vip'],
                source: 'VIP',
                reasonCode: 'vip_entitlement',
            );

            if ($vip !== null) {
                return $vip;
            }
        }

        return AccessDecision::deny('no_entitlement');
    }

    /**
     * @param list<Entitlement> $entitlements
     * @param list<string> $grantTypes
     */
    private function decisionForSource(
        array $entitlements,
        AccessDecisionContext $context,
        array $grantTypes,
        string $source,
        string $reasonCode,
    ): ?AccessDecision {
        $candidates = array_values(array_filter(
            $entitlements,
            static fn (Entitlement $entitlement): bool => in_array($entitlement->grantType, $grantTypes, true),
        ));

        if ($candidates === []) {
            return null;
        }

        usort($candidates, self::sorter(...));

        $blocked = null;
        foreach ($candidates as $candidate) {
            $scope = $this->scopeMatch($candidate, $context);
            if ($scope !== 'match') {
                $blocked ??= AccessDecision::deny(
                    reasonCode: $scope === 'excluded' ? 'scope_excluded' : 'scope_mismatch',
                    source: $source,
                    entitlementId: $candidate->id,
                    grantType: $candidate->grantType,
                    quota: $candidate->quotaSnapshot,
                    expiresAt: $candidate->expiresAt,
                    rulesVersion: $candidate->rulesVersion,
                );
                continue;
            }

            $quota = $this->quotaFor($candidate, $context);
            if (($quota['available'] ?? true) !== true) {
                $blocked ??= AccessDecision::deny(
                    reasonCode: 'quota_exhausted',
                    source: $source,
                    entitlementId: $candidate->id,
                    grantType: $candidate->grantType,
                    quota: $quota,
                    expiresAt: $candidate->expiresAt,
                    rulesVersion: $candidate->rulesVersion,
                );
                continue;
            }

            return AccessDecision::allow(
                reasonCode: $reasonCode,
                source: $source,
                entitlementId: $candidate->id,
                grantType: $candidate->grantType,
                quota: $quota === [] ? null : $quota,
                expiresAt: $candidate->expiresAt,
                rulesVersion: $candidate->rulesVersion,
            );
        }

        return $blocked;
    }

    private static function sorter(Entitlement $left, Entitlement $right): int
    {
        if ($left->priority !== $right->priority) {
            return $right->priority <=> $left->priority;
        }

        $leftExpiry = self::expiryRank($left->expiresAt);
        $rightExpiry = self::expiryRank($right->expiresAt);
        if ($leftExpiry !== $rightExpiry) {
            return $rightExpiry <=> $leftExpiry;
        }

        return $left->id <=> $right->id;
    }

    private static function expiryRank(?string $expiresAt): int
    {
        if ($expiresAt === null) {
            return PHP_INT_MAX;
        }

        $date = new DateTimeImmutable($expiresAt);
        return $date->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
    }

    private function scopeMatch(Entitlement $entitlement, AccessDecisionContext $context): string
    {
        if ($entitlement->resourceId !== null) {
            return $entitlement->resourceId === $context->resourceId ? 'match' : 'mismatch';
        }

        $scope = $this->scopePayload($entitlement);
        $excluded = self::positiveIntList($scope['excluded_resource_ids'] ?? []);
        if (in_array($context->resourceId, $excluded, true)) {
            return 'excluded';
        }

        $type = (string) ($scope['type'] ?? $entitlement->scopeType);
        if ($type === 'all') {
            return 'match';
        }

        if ($type === 'resources') {
            $resourceIds = self::positiveIntList($scope['resource_ids'] ?? $scope['resources'] ?? []);
            return in_array($context->resourceId, $resourceIds, true) ? 'match' : 'mismatch';
        }

        if ($type === 'taxonomies') {
            $termIds = self::positiveIntList($scope['taxonomy_term_ids'] ?? $scope['term_ids'] ?? $scope['category_ids'] ?? []);
            return array_values(array_intersect($context->taxonomyTermIds, $termIds)) === [] ? 'mismatch' : 'match';
        }

        return 'mismatch';
    }

    /**
     * @return array<string, mixed>
     */
    private function scopePayload(Entitlement $entitlement): array
    {
        $payload = $entitlement->scopeSnapshot;
        if (isset($payload['scope']) && is_array($payload['scope'])) {
            $payload = $payload['scope'];
        }

        if (isset($payload['rules']) && is_array($payload['rules'])) {
            $payload = array_replace($payload['rules'], $payload);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function quotaFor(Entitlement $entitlement, AccessDecisionContext $context): array
    {
        $quota = is_array($entitlement->quotaSnapshot) ? $entitlement->quotaSnapshot : [];
        if (is_callable($this->quotaResolver)) {
            $resolved = ($this->quotaResolver)($entitlement, $context);
            if ($resolved === false) {
                $quota['available'] = false;
            } elseif (is_array($resolved)) {
                $quota = array_replace($quota, $resolved);
            }
        }

        if (array_key_exists('remaining', $quota) && (int) $quota['remaining'] <= 0) {
            $quota['available'] = false;
        }

        if (! array_key_exists('available', $quota)) {
            $quota['available'] = true;
        }

        return $quota;
    }

    /**
     * @return list<int>
     */
    private static function positiveIntList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (is_numeric($value) && (int) $value > 0) {
                $ids[(string) ((int) $value)] = (int) $value;
            }
        }

        ksort($ids);
        return array_values($ids);
    }
}
