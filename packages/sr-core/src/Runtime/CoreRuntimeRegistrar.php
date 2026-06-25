<?php
declare(strict_types=1);

namespace StockResource\Core\Runtime;

use StockResource\Core\Cli\MigrationCommand;
use StockResource\Core\Content\Taxonomy\TaxonomyCatalog;
use StockResource\Core\Infrastructure\Migration\ArrayMigrationRepository;
use StockResource\Core\Infrastructure\Migration\MigrationRunner;
use StockResource\Core\Support\Http\RequestContext;
use StockResource\Core\Support\Http\RequestIdFactory;
use StockResource\Core\Support\Http\RestRequestIdMiddleware;

final readonly class CoreRuntimeRegistrar
{
    public function __construct(
        private TaxonomyCatalog $taxonomies,
        private RestRequestIdMiddleware $requestIdMiddleware,
        private MigrationCommand $migrationCommand,
    ) {
    }

    public static function defaults(): self
    {
        return new self(
            TaxonomyCatalog::defaults(),
            new RestRequestIdMiddleware(),
            new MigrationCommand(new MigrationRunner(new ArrayMigrationRepository()), []),
        );
    }

    public function register(RuntimeEnvironment $runtime): void
    {
        $this->registerTaxonomies($runtime);
        $this->registerRequestIdHeader($runtime);
        $this->registerCliCommands($runtime);
    }

    private function registerTaxonomies(RuntimeEnvironment $runtime): void
    {
        $runtime->addAction('init', function () use ($runtime): void {
            foreach ($this->taxonomies->definitions() as $definition) {
                if ($runtime->taxonomyExists($definition->name())) {
                    continue;
                }

                $runtime->registerTaxonomy($definition->name(), 'download', $definition->registrationArgs());
            }
        }, 10, 0);
    }

    private function registerRequestIdHeader(RuntimeEnvironment $runtime): void
    {
        $runtime->addFilter('rest_post_dispatch', function (mixed $response, mixed $server = null, mixed $request = null) use ($runtime): mixed {
            $requestId = RequestIdFactory::fromIncomingHeader($runtime->incomingHeader('X-Request-ID'));
            $headers = $this->requestIdMiddleware->withRequestIdHeader([], new RequestContext($requestId));

            foreach ($headers as $name => $value) {
                if (is_object($response) && method_exists($response, 'header')) {
                    $response->header($name, $value);
                    continue;
                }

                $runtime->sendHeader($name, $value);
            }

            return $response;
        }, 10, 3);
    }

    private function registerCliCommands(RuntimeEnvironment $runtime): void
    {
        if (! $runtime->cliAvailable()) {
            return;
        }

        $runtime->addCliCommand(
            'sr migrate',
            fn(array $args = [], array $assocArgs = []): int => $this->migrationCommand->migrate($assocArgs),
        );
        $runtime->addCliCommand(
            'sr status',
            fn(array $args = [], array $assocArgs = []): int => $this->migrationCommand->status(),
        );
        $runtime->addCliCommand(
            'sr schema:verify',
            fn(array $args = [], array $assocArgs = []): int => $this->migrationCommand->schemaVerify(),
        );
    }
}
