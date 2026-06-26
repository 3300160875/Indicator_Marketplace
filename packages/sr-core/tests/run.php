<?php
declare(strict_types=1);

use StockResource\Core\Plugin;
use StockResource\Core\Admin\ResourceEditor\ResourceDraft;
use StockResource\Core\Admin\ResourceEditor\ResourceEditorSectionCatalog;
use StockResource\Core\Admin\ResourceEditor\ResourcePublishGate;
use StockResource\Core\Application\ResourceService;
use StockResource\Core\Cli\MigrationCommand;
use StockResource\Core\Content\Meta\DownloadMetaCatalog;
use StockResource\Core\Content\Taxonomy\ControlledVocabulary;
use StockResource\Core\Content\Taxonomy\TaxonomyCatalog;
use StockResource\Core\Infrastructure\Migration\ArrayMigrationRepository;
use StockResource\Core\Infrastructure\Migration\Migration;
use StockResource\Core\Infrastructure\Migration\MigrationRunner;
use StockResource\Core\Runtime\CoreRuntimeRegistrar;
use StockResource\Core\Runtime\RuntimeEnvironment;
use StockResource\Core\Rest\Public\PublicResourceCollection;
use StockResource\Core\Rest\Public\PublicResourceQuery;
use StockResource\Core\Rest\Public\PublicRestError;
use StockResource\Core\Rest\Public\PublicRestRouteCatalog;
use StockResource\Core\Rest\Public\PublicTaxonomyVocabulary;
use StockResource\Core\Support\Http\RestRequestIdMiddleware;
use StockResource\Core\Version\InMemoryResourceVersionRepository;
use StockResource\Core\Version\ResourceVersion;
use StockResource\Core\Version\ResourceVersionSchemaMigration;
use StockResource\Core\Version\ResourceVersionStatus;
use StockResource\Core\Version\ResourceVersionWorkflow;

require_once dirname(__DIR__) . '/src/Plugin.php';

$sourceFiles = [
    '/src/Runtime/RuntimeEnvironment.php',
    '/src/Runtime/WordPressRuntimeEnvironment.php',
    '/src/Runtime/CoreRuntimeRegistrar.php',
    '/src/Admin/ResourceEditor/EditorSection.php',
    '/src/Admin/ResourceEditor/ResourceEditorSectionCatalog.php',
    '/src/Admin/ResourceEditor/GateIssue.php',
    '/src/Admin/ResourceEditor/PublishGateResult.php',
    '/src/Admin/ResourceEditor/ResourceDraft.php',
    '/src/Admin/ResourceEditor/ResourcePublishGate.php',
    '/src/Admin/ResourceEditor/ResourceChangeAuditPolicy.php',
    '/src/Dto/VersionView.php',
    '/src/Dto/ResourceView.php',
    '/src/Application/ResourceService.php',
    '/src/Rest/Public/PublicRestError.php',
    '/src/Rest/Public/PublicRestRoute.php',
    '/src/Rest/Public/PublicRestRouteCatalog.php',
    '/src/Rest/Public/PublicResourceQuery.php',
    '/src/Rest/Public/PublicResourceCollection.php',
    '/src/Rest/Public/PublicTaxonomyVocabulary.php',
    '/src/Content/Meta/DownloadMetaDefinition.php',
    '/src/Content/Meta/DownloadMetaCatalog.php',
    '/src/Content/Taxonomy/TaxonomyDefinition.php',
    '/src/Content/Taxonomy/TaxonomyCatalog.php',
    '/src/Content/Taxonomy/ControlledVocabulary.php',
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
    '/src/Version/ResourceVersionStatus.php',
    '/src/Version/ResourceVersionScanStatus.php',
    '/src/Version/ResourceVersion.php',
    '/src/Version/ResourceVersionRepository.php',
    '/src/Version/InMemoryResourceVersionRepository.php',
    '/src/Version/ResourceVersionWorkflowStage.php',
    '/src/Version/ResourceVersionWorkflow.php',
    '/src/Version/ResourceVersionSchemaMigration.php',
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

$editorSections = ResourceEditorSectionCatalog::defaults();
assert_true(in_array('_sr_access_mode', $editorSections->get('commercial')->fields(), true), 'resource editor has commercial section');
$publishGate = new ResourcePublishGate();
$blockedPublish = $publishGate->evaluate(ResourceDraft::fromArray([
    'post_title' => '稳赚指标',
    'post_excerpt' => '',
    'screenshot_count' => 0,
    'meta' => [
        '_sr_access_mode' => 'unavailable',
        '_sr_future_function_status' => 'unknown',
        '_sr_l2_required' => 'unknown',
        '_sr_rights_status' => 'pending',
        '_sr_risk_level' => 'blocked',
    ],
]));
assert_same(false, $blockedPublish->canPublish(), 'resource publish gate blocks incomplete drafts');
assert_true(in_array('prohibited_claim', $blockedPublish->issueCodes(), true), 'resource publish gate flags prohibited claims');
assert_true(in_array('usage_scenarios_required', $blockedPublish->issueCodes(), true), 'resource publish gate requires usage scenarios');
assert_true(in_array('limitations_required', $blockedPublish->issueCodes(), true), 'resource publish gate requires limitations and risk text');

$paidWithoutRightsRecord = $publishGate->evaluate(ResourceDraft::fromArray([
    'post_title' => '通达信趋势指标',
    'post_excerpt' => '用于趋势识别的指标资源。',
    'post_content' => '<p>安装后用于辅助趋势观察，不构成投资建议。</p>',
    'screenshot_count' => 1,
    'price_configured' => true,
    'taxonomies' => [
        'download_category' => ['vip-formula'],
        'sr_platform' => ['tongdaxin'],
        'sr_indicator_type' => ['sub-chart'],
        'sr_content_type' => ['indicator'],
    ],
    'meta' => [
        '_sr_access_mode' => 'purchase',
        '_sr_software_versions' => ['通达信 7.60'],
        '_sr_device' => 'desktop',
        '_sr_os' => 'windows',
        '_sr_file_format' => 'tn6',
        '_sr_charset' => 'gbk',
        '_sr_source_included' => 'yes',
        '_sr_future_function_status' => 'none',
        '_sr_l2_required' => 'no',
        '_sr_install_steps' => '<p>导入公式管理器。</p>',
        '_sr_usage_scenarios' => '<p>趋势观察。</p>',
        '_sr_limitations' => '<p>仅辅助判断。</p>',
        '_sr_current_version_id' => 88,
        '_sr_rights_status' => 'approved',
        '_sr_rights_record_id' => 0,
        '_sr_risk_level' => 'medium',
        '_sr_disclaimer_version' => 'risk-v1',
    ],
]));
assert_same(['rights_record_required'], $paidWithoutRightsRecord->issueCodes(), 'paid resources require rights evidence records');

$versionMigration = ResourceVersionSchemaMigration::create();
assert_true(str_contains($versionMigration->sql('wp_'), 'CREATE TABLE wp_sr_resource_versions'), 'resource version migration creates prefixed table');
assert_true(str_contains($versionMigration->sql('wp_'), 'KEY idx_resource_current (resource_id, is_current)'), 'resource version migration has current index');
assert_true(str_contains($versionMigration->sql('wp_'), "scan_status VARCHAR(24) NOT NULL DEFAULT 'pending'"), 'resource version migration has scan status default');

$versionWorkflow = ResourceVersionWorkflow::defaults();
assert_true($versionWorkflow->canTransition(ResourceVersionStatus::Review, ResourceVersionStatus::Active), 'resource version workflow allows reviewed versions to activate');
assert_same(true, $versionWorkflow->stages()['activate']->usesTransactionLock, 'resource version activation requires transaction lock');

$versionRepository = new InMemoryResourceVersionRepository();
$versionRepository->create(ResourceVersion::fromArray([
    'id' => 1,
    'resource_id' => 1001,
    'version_label' => '1.0.0',
    'status' => 'review',
    'scan_status' => 'clean',
    'created_by' => 501,
    'created_at' => '2026-06-25 10:00:00',
    'updated_at' => '2026-06-25 10:00:00',
]));
$versionRepository->create(ResourceVersion::fromArray([
    'id' => 2,
    'resource_id' => 1001,
    'version_label' => '1.1.0',
    'status' => 'review',
    'scan_status' => 'clean',
    'created_by' => 501,
    'created_at' => '2026-06-25 10:01:00',
    'updated_at' => '2026-06-25 10:01:00',
]));
$directCurrentWasRejected = false;
try {
    $versionRepository->create(ResourceVersion::fromArray([
        'id' => 3,
        'resource_id' => 1001,
        'version_label' => '2.0.0',
        'status' => 'draft',
        'is_current' => true,
        'scan_status' => 'pending',
        'created_by' => 501,
        'created_at' => '2026-06-25 10:02:00',
        'updated_at' => '2026-06-25 10:02:00',
    ]));
} catch (RuntimeException) {
    $directCurrentWasRejected = true;
}
assert_same(true, $directCurrentWasRejected, 'resource versions cannot bypass activation lock on create');
$versionRepository->activateCurrent(1001, 2, 601, '2026-06-25 10:02:00');
assert_same(2, $versionRepository->currentForResource(1001)?->id, 'resource version repository exposes one current version');
$versionRepository->activateCurrent(1001, 1, 602, '2026-06-25 10:03:00');
assert_same(1, $versionRepository->currentForResource(1001)?->id, 'resource version repository switches current version transactionally');
assert_same([1001, 1001], $versionRepository->transactionLockLog(), 'resource version activation records transaction locks');

$failedScanWasRejected = false;
try {
    $versionRepository->create(ResourceVersion::fromArray([
        'id' => 4,
        'resource_id' => 1002,
        'version_label' => '1.0.0',
        'status' => 'review',
        'scan_status' => 'failed',
        'created_by' => 501,
        'created_at' => '2026-06-25 10:02:00',
        'updated_at' => '2026-06-25 10:02:00',
    ]));
    $versionRepository->activateCurrent(1002, 4, 601, '2026-06-25 10:03:00');
} catch (RuntimeException) {
    $failedScanWasRejected = true;
}
assert_same(true, $failedScanWasRejected, 'resource versions require clean scan before activation');

$resourceService = new ResourceService();
$publicVersion = ResourceVersion::fromArray([
    'id' => 88,
    'resource_id' => 1001,
    'version_label' => '1.2.0',
    'status' => 'active',
    'is_current' => true,
    'storage_key' => 'resource/1001/private.zip',
    'file_size' => 4096,
    'sha256' => str_repeat('c', 64),
    'compatibility' => ['platform' => 'tongdaxin'],
    'scan_status' => 'clean',
    'scan_result' => ['internal_notes' => 'hidden'],
    'release_notes' => '公开版本说明。',
    'created_by' => 501,
    'approved_by' => 601,
    'activated_at' => '2026-06-25 10:00:00',
    'created_at' => '2026-06-25 09:00:00',
    'updated_at' => '2026-06-25 10:00:00',
]);
$resourceView = $resourceService->publicView([
    'id' => 1001,
    'post_status' => 'publish',
    'slug' => 'tdx-trend',
    'title' => '通达信趋势指标',
    'excerpt' => '用于趋势识别的指标资源。',
    'content' => '<p>公开说明。</p>',
    'taxonomies' => ['sr_platform' => ['tongdaxin'], 'sr_internal_review' => ['hidden']],
    'meta' => [
        '_sr_access_mode' => 'purchase',
        '_sr_software_versions' => ['通达信 7.60'],
        '_sr_risk_level' => 'medium',
        '_sr_internal_notes' => 'hidden',
        'storage_key' => 'resource/1001/leak.zip',
    ],
], $publicVersion);
assert_true($resourceView !== null, 'resource service returns DTO for published resources');
$resourcePayload = $resourceView->toArray();
assert_same(88, $resourcePayload['current_version']['id'], 'resource service exposes current version DTO');
assert_true(! isset($resourcePayload['taxonomies']['sr_internal_review']), 'resource service excludes unknown taxonomies');
assert_same(['通达信 7.60'], $resourcePayload['meta']['software_versions'], 'resource service maps public meta keys');
assert_true(! str_contains(json_encode($resourcePayload, JSON_THROW_ON_ERROR), '"_sr_'), 'resource DTO excludes raw post meta keys');
assert_true(! str_contains(json_encode($resourcePayload, JSON_THROW_ON_ERROR), 'storage_key'), 'resource DTO excludes storage keys');
assert_true(! str_contains(json_encode($resourcePayload, JSON_THROW_ON_ERROR), 'sha256'), 'resource DTO excludes file hashes');
assert_true(! str_contains(json_encode($resourcePayload, JSON_THROW_ON_ERROR), 'internal_notes'), 'resource DTO excludes internal notes');

$draftView = $resourceService->publicView([
    'id' => 1002,
    'post_status' => 'draft',
    'meta' => ['_sr_access_mode' => 'free'],
], $publicVersion);
assert_same(null, $draftView, 'resource service blocks unpublished resources');

$publicRoutes = PublicRestRouteCatalog::defaults()->routes();
assert_same(['GET /resources', 'GET /resources/{idOrSlug}', 'GET /taxonomies'], array_map(
    static fn($route): string => $route->method() . ' ' . $route->path(),
    $publicRoutes,
), 'public REST route catalog exposes approved routes');
assert_same(true, $publicRoutes[0]->permissionCallback()(), 'public REST routes expose explicit public permission callback');

$publicQuery = PublicResourceQuery::fromArray([
    'search' => ' 通达信  趋势 ',
    'platform' => 'tongdaxin',
    'indicator_type' => 'sub-chart',
    'page' => '2',
    'per_page' => '24',
    'sort' => 'title_asc',
]);
assert_same([
    'indicator_type' => 'sub-chart',
    'page' => 2,
    'per_page' => 24,
    'platform' => 'tongdaxin',
    'search' => '通达信 趋势',
    'sort' => 'title_asc',
], $publicQuery->canonicalParams(), 'public REST query canonicalizes filters deterministically');

$invalidFilterWasRejected = false;
try {
    PublicResourceQuery::fromArray(['unknown' => 'bad']);
} catch (PublicRestError $error) {
    $invalidFilterWasRejected = $error->code() === 'sr_invalid_filter' && $error->status() === 400;
}
assert_same(true, $invalidFilterWasRejected, 'public REST query rejects unknown filters with stable error code');

$publicCollection = PublicResourceCollection::fromViews([$resourceView, $draftView]);
$publicList = $publicCollection->list(PublicResourceQuery::fromArray(['platform' => 'tongdaxin']));
assert_same(1, $publicList['pagination']['total'], 'public REST list excludes unpublished resources');
assert_same('tdx-trend', $publicCollection->detail('tdx-trend')['data']['slug'], 'public REST detail resolves by slug');

$missingDetailWasRejected = false;
try {
    $publicCollection->detail('missing-resource');
} catch (PublicRestError $error) {
    $missingDetailWasRejected = $error->code() === 'sr_resource_unavailable' && $error->status() === 404;
}
assert_same(true, $missingDetailWasRejected, 'public REST detail hides missing resources behind unavailable error');

$publicVocabulary = PublicTaxonomyVocabulary::fromCatalog(TaxonomyCatalog::defaults(), ControlledVocabulary::defaults())->toArray();
assert_same('platform', $publicVocabulary['data']['sr_platform']['rest_key'], 'public taxonomy vocabulary exposes stable REST keys');

echo "sr-core runtime tests: ok\n";
