<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Rest\Me;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use StockResource\Entitlements\Infrastructure\Repository\Entitlement;
use StockResource\Entitlements\Infrastructure\Repository\EntitlementRepository;
use StockResource\Entitlements\Infrastructure\Repository\EntitlementStatus;

final readonly class MeEntitlementsController
{
    private mixed $rulesVersionResolver;

    public function __construct(
        private EntitlementRepository $repository,
        private ?MeEntitlementsCacheStore $cache = null,
        mixed $rulesVersionResolver = null,
    ) {
        $this->rulesVersionResolver = $rulesVersionResolver;
    }

    /**
     * Builds the current user's membership/entitlement projection for the account center.
     *
     * @return array<string, mixed>
     */
    public function show(int $currentUserId, string $atUtc): array
    {
        $this->assertCurrentUser($currentUserId);
        $this->assertUtc($atUtc);

        $rulesVersion = $this->rulesVersion();
        $cacheKey = self::cacheKey($currentUserId, $rulesVersion);
        $cached = $this->cache?->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $items = array_map(
            fn (Entitlement $entitlement): array => $this->project($entitlement, $atUtc),
            $this->repository->forUser($currentUserId),
        );
        usort($items, self::sortRows(...));

        $response = [
            'state' => $items === [] ? 'empty' : 'ready',
            'user_id' => $currentUserId,
            'generated_at' => $atUtc,
            'cache' => [
                'key' => $cacheKey,
                'rules_version' => $rulesVersion,
                'invalidates' => [$cacheKey],
            ],
            'entitlements' => $items,
        ];

        $this->cache?->set($cacheKey, $response);

        return $response;
    }

    public static function cacheKey(int $currentUserId, string $rulesVersion): string
    {
        if ($currentUserId < 1) {
            throw new InvalidArgumentException('current_user_id must be positive.');
        }

        $rulesVersion = trim($rulesVersion);
        if ($rulesVersion === '') {
            throw new InvalidArgumentException('rules_version is required.');
        }

        return 'sr:me:entitlements:'.$currentUserId.':'.$rulesVersion;
    }

    /**
     * @return list<string>
     */
    public static function invalidationKeysForUser(int $currentUserId, string $rulesVersion): array
    {
        return [self::cacheKey($currentUserId, $rulesVersion)];
    }

    /**
     * @return list<string>
     */
    public function invalidateForUser(int $currentUserId): array
    {
        $keys = self::invalidationKeysForUser($currentUserId, $this->rulesVersion());
        foreach ($keys as $key) {
            $this->cache?->delete($key);
        }

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private function project(Entitlement $entitlement, string $atUtc): array
    {
        $scope = $this->scopePayload($entitlement);
        $plan = $this->planPayload($entitlement, $scope);
        $quota = $this->quotaPayload($entitlement);

        return [
            'id' => $entitlement->id,
            'status' => $this->statusFor($entitlement, $atUtc),
            'grant_type' => $entitlement->grantType,
            'source_type' => $entitlement->sourceType,
            'plan' => $plan,
            'starts_at' => $entitlement->startsAt,
            'expires_at' => $entitlement->expiresAt,
            'scope' => $this->publicScope($entitlement, $scope),
            'quota' => $quota,
            'rules_version' => $entitlement->rulesVersion,
            'revoked_at' => $entitlement->revokedAt,
            'updated_at' => $entitlement->updatedAt,
        ];
    }

    /**
     * @return array{code: string, name: string, download_id: int|null}
     */
    private function planPayload(Entitlement $entitlement, array $scope): array
    {
        $plan = is_array($scope['plan'] ?? null) ? $scope['plan'] : [];
        $code = trim((string) ($plan['code'] ?? $plan['slug'] ?? $entitlement->grantType));
        $name = trim((string) ($plan['name'] ?? $plan['label'] ?? $code));

        return [
            'code' => $code === '' ? $entitlement->grantType : $code,
            'name' => $name === '' ? $entitlement->grantType : $name,
            'download_id' => $entitlement->planDownloadId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function publicScope(Entitlement $entitlement, array $scope): array
    {
        $type = trim((string) ($scope['type'] ?? $entitlement->scopeType));
        $payload = [
            'type' => $type === '' ? $entitlement->scopeType : $type,
            'resource_id' => $entitlement->resourceId,
        ];

        foreach (['resource_ids', 'taxonomy_term_ids', 'term_ids', 'category_ids', 'excluded_resource_ids'] as $key) {
            $ids = self::positiveIntList($scope[$key] ?? []);
            if ($ids !== []) {
                $payload[$key] = $ids;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, int|string|null>
     */
    private function quotaPayload(Entitlement $entitlement): array
    {
        $snapshot = is_array($entitlement->quotaSnapshot) ? $entitlement->quotaSnapshot : [];
        $limit = self::nullableInt($snapshot['limit'] ?? $snapshot['limit_snapshot'] ?? null);
        $used = self::nullableInt($snapshot['used'] ?? $snapshot['used_count'] ?? null);
        $reserved = self::nullableInt($snapshot['reserved'] ?? $snapshot['reserved_count'] ?? null);
        $remaining = self::nullableInt($snapshot['remaining'] ?? null);

        if ($remaining === null && $limit !== null && $used !== null) {
            $remaining = max(0, $limit - $used - ($reserved ?? 0));
        }

        return [
            'period_type' => self::nullableString($snapshot['period_type'] ?? null),
            'period_key' => self::nullableString($snapshot['period_key'] ?? null),
            'limit' => $limit,
            'used' => $used,
            'reserved' => $reserved,
            'remaining' => $remaining,
            'reset_at' => self::nullableString($snapshot['reset_at'] ?? null),
        ];
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

    private function statusFor(Entitlement $entitlement, string $atUtc): string
    {
        if ($entitlement->status === EntitlementStatus::Revoked) {
            return 'revoked';
        }
        if ($entitlement->status !== EntitlementStatus::Active) {
            return $entitlement->status->value;
        }

        return $entitlement->isActive($atUtc) ? 'active' : 'expired';
    }

    private function rulesVersion(): string
    {
        if (is_callable($this->rulesVersionResolver)) {
            $resolved = trim((string) ($this->rulesVersionResolver)());
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return 'rules-v1';
    }

    private function assertCurrentUser(int $currentUserId): void
    {
        if ($currentUserId < 1) {
            throw new InvalidArgumentException('current_user_id must be positive.');
        }
    }

    private function assertUtc(string $atUtc): void
    {
        if (DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $atUtc) === false) {
            throw new InvalidArgumentException('at_utc must be ISO-8601.');
        }
    }

    private static function sortRows(array $left, array $right): int
    {
        $leftRank = self::statusRank((string) ($left['status'] ?? ''));
        $rightRank = self::statusRank((string) ($right['status'] ?? ''));
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        $leftExpiry = self::timestampRank($left['expires_at'] ?? null);
        $rightExpiry = self::timestampRank($right['expires_at'] ?? null);
        if ($leftExpiry !== $rightExpiry) {
            return $rightExpiry <=> $leftExpiry;
        }

        return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
    }

    private static function statusRank(string $status): int
    {
        return match ($status) {
            'active' => 0,
            'pending' => 1,
            'expired' => 2,
            'revoked' => 3,
            default => 4,
        };
    }

    private static function timestampRank(mixed $datetime): int
    {
        if (! is_string($datetime) || trim($datetime) === '') {
            return PHP_INT_MAX;
        }

        $parsed = new DateTimeImmutable($datetime);

        return $parsed->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
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

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}

interface MeEntitlementsCacheStore
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array;

    /**
     * @param array<string, mixed> $value
     */
    public function set(string $key, array $value): void;

    public function delete(string $key): void;
}

final class InMemoryMeEntitlementsCacheStore implements MeEntitlementsCacheStore
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $items = [];

    public function get(string $key): ?array
    {
        return $this->items[$key] ?? null;
    }

    public function set(string $key, array $value): void
    {
        $this->items[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($this->items[$key]);
    }
}

final readonly class WordPressMeEntitlementsCacheStore implements MeEntitlementsCacheStore
{
    public function __construct(
        private string $group = 'stock_resource_me_entitlements',
        private int $ttlSeconds = 300,
    ) {
    }

    public function get(string $key): ?array
    {
        if (! function_exists('wp_cache_get')) {
            return null;
        }

        $value = wp_cache_get($key, $this->group);

        return is_array($value) ? $value : null;
    }

    public function set(string $key, array $value): void
    {
        if (! function_exists('wp_cache_set')) {
            return;
        }

        wp_cache_set($key, $value, $this->group, $this->ttlSeconds);
    }

    public function delete(string $key): void
    {
        if (! function_exists('wp_cache_delete')) {
            return;
        }

        wp_cache_delete($key, $this->group);
    }
}

final readonly class MeEntitlementsRouteRegistrar
{
    public function __construct(
        private MeEntitlementsController $controller,
        private string $namespace = 'stock-resource/v1',
        private string $route = '/me/entitlements',
    ) {
    }

    public function register(): void
    {
        if (! function_exists('register_rest_route')) {
            throw new RuntimeException('register_rest_route is not available.');
        }

        register_rest_route($this->namespace, $this->route, [
            'methods' => 'GET',
            'callback' => $this->handle(...),
            'permission_callback' => $this->permission(...),
        ]);
    }

    public function permission(): bool
    {
        if (function_exists('is_user_logged_in')) {
            return (bool) is_user_logged_in();
        }

        return $this->currentUserId() > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(mixed $request = null): array
    {
        unset($request);

        return $this->controller->show(
            currentUserId: $this->currentUserId(),
            atUtc: gmdate(DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, string>
     */
    public function routeSpec(): array
    {
        return [
            'namespace' => $this->namespace,
            'route' => $this->route,
            'methods' => 'GET',
        ];
    }

    private function currentUserId(): int
    {
        if (! function_exists('get_current_user_id')) {
            return 0;
        }

        return max(0, (int) get_current_user_id());
    }
}
