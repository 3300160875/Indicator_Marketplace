<?php
declare(strict_types=1);

namespace StockResource\Core\Rest\Public;

final readonly class PublicRestRouteCatalog
{
    private const NAMESPACE = 'sr/v1';

    /** @param list<PublicRestRoute> $routes */
    private function __construct(private array $routes)
    {
    }

    public static function defaults(): self
    {
        $public = static fn(): bool => true;

        return new self([
            new PublicRestRoute(
                namespace: self::NAMESPACE,
                method: 'GET',
                path: '/resources',
                operationId: 'listResources',
                arguments: PublicResourceQuery::argumentSchema(),
                permissionCallback: $public,
            ),
            new PublicRestRoute(
                namespace: self::NAMESPACE,
                method: 'GET',
                path: '/resources/{idOrSlug}',
                operationId: 'getResource',
                arguments: [
                    'idOrSlug' => [
                        'required' => true,
                        'type' => 'string',
                        'minLength' => 1,
                        'maxLength' => 200,
                    ],
                ],
                permissionCallback: $public,
            ),
            new PublicRestRoute(
                namespace: self::NAMESPACE,
                method: 'GET',
                path: '/taxonomies',
                operationId: 'listTaxonomies',
                arguments: [],
                permissionCallback: $public,
            ),
        ]);
    }

    /**
     * @return list<PublicRestRoute>
     */
    public function routes(): array
    {
        return $this->routes;
    }
}
