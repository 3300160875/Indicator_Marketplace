<?php
declare(strict_types=1);

use StockResource\Core\Plugin;
use StockResource\Core\Cli\MigrationCommand;
use StockResource\Core\Content\Meta\DownloadMetaCatalog;
use StockResource\Core\Content\Taxonomy\TaxonomyCatalog;
use StockResource\Core\Infrastructure\Migration\ArrayMigrationRepository;
use StockResource\Core\Infrastructure\Migration\Migration;
use StockResource\Core\Infrastructure\Migration\MigrationRunner;
use StockResource\Core\Runtime\CoreRuntimeRegistrar;
use StockResource\Core\Runtime\RuntimeEnvironment;
use StockResource\Core\Support\Http\RestRequestIdMiddleware;

require_once dirname(__DIR__) . '/src/Plugin.php';

$sourceFiles = [
    '/src/Runtime/RuntimeEnvironment.php',
    '/src/Runtime/WordPressRuntimeEnvironment.php',
    '/src/Runtime/CoreRuntimeRegistrar.php',
    '/src/Content/Meta/DownloadMetaDefinition.php',
    '/src/Content/Meta/DownloadMetaCatalog.php',
    '/src/Content/Taxonomy/TaxonomyDefinition.php',
    '/src/Content/Taxonomy/TaxonomyCatalog.php',
    '/src/Infrastructure/Migration/Migration.php',
    '/src/Infrastructure/Migration/MigrationRecord.php',
    '/src/Infrastructure/Migration/MigrationRepository.php',
    '/src/Infrastructure/Migration/ArrayMigrationRepository.php',
    '/src/Infrastructure/Migration/MigrationResult.php',
    '/src/Infrastructure/Migration/MigrationRunner.php',
    '/src/Cli/MigrationCommand.php',
    '/src/Support/Http/RequestContext.php',
    '/src/Support/Http/RequestIdFactory.php',
    '/src/Support/Http/RestRequestIdMiddleware.php',
];

foreach ($sourceFiles as $sourceFile) {
    $path = dirname(__DIR__) . $sourceFile;
    if (is_readable($path)) {
        require_once $path;
    }
}

function assert_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

$entry = file_get_contents(dirname(__DIR__) . '/sr-core.php');
assert_true($entry !== false, 'plugin entry file is readable');
assert_true(str_contains($entry, 'Plugin Name: Stock Resource Core'), 'plugin header declares the core plugin name');
assert_true(str_contains($entry, 'Requires Plugins: easy-digital-downloads'), 'plugin header declares EDD dependency');
assert_true(str_contains($entry, 'Requires PHP: 8.3'), 'plugin header declares PHP requirement');

assert_same('sr-core', Plugin::slug(), 'plugin exposes stable slug');
assert_same('0.1.0', Plugin::version(), 'plugin exposes skeleton version');
assert_same(['easy-digital-downloads/easy-digital-downloads.php'], Plugin::requiredPlugins(), 'plugin declares runtime plugin dependency');
assert_same(['StockResource\\Platform\\BootstrapPlugin'], Plugin::requiredClasses(), 'plugin declares platform bootstrap dependency');
assert_same([], Plugin::missingRuntimeDependencies(
    pluginActive: static fn(string $plugin): bool => $plugin === 'easy-digital-downloads/easy-digital-downloads.php',
    classExists: static fn(string $class): bool => $class === 'StockResource\\Platform\\BootstrapPlugin',
), 'plugin reports no missing dependencies when EDD and bootstrap are available');
assert_same(['plugin:easy-digital-downloads/easy-digital-downloads.php', 'class:StockResource\\Platform\\BootstrapPlugin'], Plugin::missingRuntimeDependencies(
    pluginActive: static fn(string $plugin): bool => false,
    classExists: static fn(string $class): bool => false,
), 'plugin reports missing EDD and bootstrap without throwing');

assert_true(interface_exists(RuntimeEnvironment::class), 'runtime environment contract exists for WordPress wiring');

$makeRuntime = static fn(): RuntimeEnvironment => new class implements RuntimeEnvironment {
    /** @var array<string, list<callable>> */
    public array $actions = [];
    /** @var array<string, list<callable>> */
    public array $filters = [];
    /** @var array<string, array{objectType: string, args: array<string, mixed>}> */
    public array $taxonomies = [];
    /** @var list<string> */
    public array $cliCommands = [];
    /** @var array<string, mixed> */
    public array $cliCallbacks = [];
    /** @var array<string, string> */
    public array $incomingHeaders = ['X-Request-ID' => '123e4567-e89b-42d3-a456-426614174000'];
    /** @var array<string, string> */
    public array $sentHeaders = [];

    public function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        $this->actions[$hook][] = $callback;
    }

    public function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        $this->filters[$hook][] = $callback;
    }

    /**
     * @param array<string, mixed> $args
     */
    public function registerTaxonomy(string $taxonomy, string $objectType, array $args): void
    {
        $this->taxonomies[$taxonomy] = ['objectType' => $objectType, 'args' => $args];
    }

    public function taxonomyExists(string $taxonomy): bool
    {
        return false;
    }

    public function cliAvailable(): bool
    {
        return true;
    }

    public function addCliCommand(string $name, mixed $command): void
    {
        $this->cliCommands[] = $name;
        $this->cliCallbacks[$name] = $command;
    }

    public function incomingHeader(string $name): ?string
    {
        return $this->incomingHeaders[$name] ?? null;
    }

    public function sendHeader(string $name, string $value): void
    {
        $this->sentHeaders[$name] = $value;
    }
};
$runtime = $makeRuntime();

assert_true(Plugin::boot(
    runtime: $runtime,
    pluginActive: static fn(string $plugin): bool => $plugin === 'easy-digital-downloads/easy-digital-downloads.php',
    classExists: static fn(string $class): bool => $class === 'StockResource\\Platform\\BootstrapPlugin',
), 'plugin boots and wires runtime when dependencies are available');
assert_true(isset($runtime->actions['init']), 'plugin registers taxonomy runtime hook on init');
assert_true(isset($runtime->filters['rest_post_dispatch']), 'plugin registers REST request id header filter');
assert_same(['sr migrate', 'sr status', 'sr schema:verify'], $runtime->cliCommands, 'plugin registers WP-CLI migration commands');

($runtime->actions['init'][0])();
assert_true(isset($runtime->taxonomies['sr_platform']), 'taxonomy init hook registers platform taxonomy');
assert_same('download', $runtime->taxonomies['sr_platform']['objectType'], 'taxonomies are registered against EDD downloads');
assert_same(true, $runtime->taxonomies['sr_platform']['args']['show_in_rest'], 'taxonomy registration keeps REST visibility');

$response = new class {
    /** @var array<string, string> */
    public array $headers = [];

    public function header(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }
};
$filteredResponse = ($runtime->filters['rest_post_dispatch'][0])($response);
assert_same($response, $filteredResponse, 'REST request id filter returns the response object');
assert_same('123e4567-e89b-42d3-a456-426614174000', $response->headers['X-Request-ID'], 'REST response receives incoming request id header');

$blockedRuntime = $makeRuntime();
assert_same(false, Plugin::boot(
    runtime: $blockedRuntime,
    pluginActive: static fn(string $plugin): bool => false,
    classExists: static fn(string $class): bool => false,
), 'plugin refuses to wire runtime when dependencies are missing');
assert_same([], $blockedRuntime->actions, 'missing dependencies leave runtime hooks untouched');

$failingMigration = new class implements Migration {
    public function version(): string
    {
        return '202606250001';
    }

    public function checksum(): string
    {
        return str_repeat('a', 64);
    }

    public function description(): string
    {
        return 'migration that must not run during dry-run';
    }

    public function up(): array
    {
        throw new RuntimeException('dry-run was not passed through WP-CLI assoc args');
    }
};
$cliRuntime = $makeRuntime();
(new CoreRuntimeRegistrar(
    TaxonomyCatalog::defaults(),
    new RestRequestIdMiddleware(),
    new MigrationCommand(new MigrationRunner(new ArrayMigrationRepository()), [$failingMigration]),
))->register($cliRuntime);
assert_true(is_callable($cliRuntime->cliCallbacks['sr migrate']), 'WP-CLI migrate callback is callable');
assert_same(0, $cliRuntime->cliCallbacks['sr migrate']([], ['dry-run' => true]), 'WP-CLI migrate wrapper maps assoc args');

$entryProbe = tempnam(sys_get_temp_dir(), 'sr-core-entry-');
assert_true(is_string($entryProbe), 'entry probe temp file is created');
$entryPath = var_export(dirname(__DIR__) . '/sr-core.php', true);
$entryProbeScript = str_replace('__SR_CORE_ENTRY__', $entryPath, <<<'PHP'
<?php
declare(strict_types=1);

$GLOBALS['sr_probe'] = ['actions' => [], 'filters' => []];

function is_plugin_active(string $plugin): bool
{
    return $plugin === 'easy-digital-downloads/easy-digital-downloads.php';
}

function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
{
    $GLOBALS['sr_probe']['actions'][$hook] = ['priority' => $priority, 'acceptedArgs' => $acceptedArgs];
}

function add_filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
{
    $GLOBALS['sr_probe']['filters'][$hook] = ['priority' => $priority, 'acceptedArgs' => $acceptedArgs];
}

require __SR_CORE_ENTRY__;

if (! class_exists('StockResource\\Core\\Plugin')) {
    throw new RuntimeException('sr-core entry did not load Plugin class');
}

if (! isset($GLOBALS['sr_probe']['actions']['init'])) {
    throw new RuntimeException('sr-core entry did not register init action');
}

if (! isset($GLOBALS['sr_probe']['filters']['rest_post_dispatch'])) {
    throw new RuntimeException('sr-core entry did not register REST request id filter');
}
PHP
);
file_put_contents($entryProbe, $entryProbeScript);
exec(PHP_BINARY . ' ' . escapeshellarg($entryProbe), $entryProbeOutput, $entryProbeExitCode);
unlink($entryProbe);
assert_same(0, $entryProbeExitCode, 'sr-core entry loads local classes and registers runtime hooks');

$metaCatalog = DownloadMetaCatalog::defaults();
assert_same(23, count($metaCatalog->definitions()), 'download resource meta catalog includes all SR-014 fields');
assert_same('unavailable', $metaCatalog->get('_sr_access_mode')->sanitize('invalid'), 'invalid access mode falls back to unavailable');
assert_same('unknown', $metaCatalog->get('_sr_future_function_status')->sanitize(false), 'future function unknown is not coerced to false');
assert_same(false, $metaCatalog->get('_sr_rights_record_id')->registrationArgs()['show_in_rest'], 'rights record id is not exposed in REST');
assert_same(false, $metaCatalog->get('_sr_risk_level')->registrationArgs()['show_in_rest'], 'raw risk level is not exposed in REST');
assert_true(is_callable($metaCatalog->get('_sr_access_mode')->registrationArgs()['sanitize_callback']), 'meta fields expose sanitize callbacks');
assert_true(is_callable($metaCatalog->get('_sr_access_mode')->registrationArgs()['auth_callback']), 'meta fields expose auth callbacks');

echo "sr-core runtime tests: ok\n";
