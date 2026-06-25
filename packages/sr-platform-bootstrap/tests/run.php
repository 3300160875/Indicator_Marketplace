<?php
declare(strict_types=1);

use StockResource\Platform\BootstrapPlugin;
use StockResource\Platform\Container\Container;
use StockResource\Platform\Dependency\DependencyChecker;
use StockResource\Platform\Dependency\Requirement;
use StockResource\Platform\Feature\FeatureFlags;
use StockResource\Platform\Provider\ServiceProvider;
use StockResource\Platform\Runtime\Runtime;

$src = dirname(__DIR__) . '/src';
foreach ([
    '/Runtime/Runtime.php',
    '/Dependency/Requirement.php',
    '/Dependency/DependencyReport.php',
    '/Dependency/DependencyChecker.php',
    '/Feature/FeatureFlags.php',
    '/Container/Container.php',
    '/Provider/ServiceProvider.php',
    '/Provider/PlatformServiceProvider.php',
    '/Admin/AdminNoticeRenderer.php',
    '/BootstrapPlugin.php',
] as $file) {
    require_once $src . $file;
}

function assert_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function assert_false(bool $condition, string $message): void
{
    assert_true(! $condition, $message);
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

final class FakeRuntime implements Runtime
{
    /** @var array<string, string> */
    public array $pluginVersions = [];

    /** @var array<string, mixed> */
    public array $options = [];

    /** @var list<array{hook: string, callback: callable}> */
    public array $actions = [];

    /** @var list<array{type: string, message: string}> */
    public array $adminNotices = [];

    public function __construct(
        private string $phpVersion = '8.3.10',
        private string $wpVersion = '6.8.2',
        private bool $admin = true,
    ) {
    }

    public function phpVersion(): string
    {
        return $this->phpVersion;
    }

    public function wordpressVersion(): ?string
    {
        return $this->wpVersion;
    }

    public function pluginVersion(string $pluginFile): ?string
    {
        return $this->pluginVersions[$pluginFile] ?? null;
    }

    public function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function addAction(string $hook, callable $callback): void
    {
        $this->actions[] = ['hook' => $hook, 'callback' => $callback];
    }

    public function adminNotice(string $type, string $message): void
    {
        $this->adminNotices[] = ['type' => $type, 'message' => $message];
    }
}

final class RecordingProvider implements ServiceProvider
{
    public bool $registered = false;

    public function register(Container $container, FeatureFlags $features): void
    {
        $this->registered = true;
        $container->set('recorded.feature.enabled', $features->enabled('SR_PAID_DOWNLOADS_ENABLED'));
    }
}

$runtime = new FakeRuntime();
$runtime->pluginVersions['easy-digital-downloads/easy-digital-downloads.php'] = '3.6.1';
$checker = new DependencyChecker([
    Requirement::php('8.3.0'),
    Requirement::wordpress('6.8.0'),
    Requirement::plugin('easy-digital-downloads/easy-digital-downloads.php', '3.6.0', 'Easy Digital Downloads'),
]);
$report = $checker->check($runtime);
assert_true($report->passed(), 'dependency report passes when PHP, WordPress and EDD satisfy minimum versions');
assert_same([], $report->failures(), 'passing dependency report has no failures');

$runtimeWithMissingEdd = new FakeRuntime();
$missingReport = $checker->check($runtimeWithMissingEdd);
assert_false($missingReport->passed(), 'dependency report fails when EDD is missing');
assert_true(
    str_contains($missingReport->failures()[0], 'Easy Digital Downloads'),
    'dependency failure names the missing plugin',
);

$features = FeatureFlags::fromOptions([
    'SR_PAID_DOWNLOADS_ENABLED' => '1',
    'SR_MANUAL_PAYMENT_ENABLED' => false,
    'unknown_flag' => true,
]);
assert_true($features->enabled('SR_PAID_DOWNLOADS_ENABLED'), 'feature flags coerce enabled string options');
assert_false($features->enabled('SR_MANUAL_PAYMENT_ENABLED'), 'feature flags keep disabled options disabled');
assert_false($features->enabled('unknown_flag'), 'feature flags ignore undeclared options');
assert_same(
    [
        'SR_PAYMENTS_ENABLED' => false,
        'SR_MANUAL_PAYMENT_ENABLED' => false,
        'SR_UPLOAD_PROOFS_ENABLED' => false,
        'SR_PAID_DOWNLOADS_ENABLED' => true,
        'SR_PRIVATE_DOWNLOADS_ENABLED' => true,
        'SR_DOWNLOAD_TOKEN_ISSUE_ENABLED' => true,
        'SR_STRICT_RIGHTS_GATE' => true,
        'SR_CONTENT_RESTRICTION_ENABLED' => false,
        'SR_OUTBOX_WORKER_ENABLED' => true,
    ],
    $features->all(),
    'feature flags expose the declared platform flags with contract defaults',
);

$container = new Container();
$container->set('answer', 42);
$container->factory('computed', fn(Container $c): int => $c->get('answer') + 1);
assert_true($container->has('answer'), 'container detects concrete services');
assert_same(42, $container->get('answer'), 'container returns concrete services');
assert_same(43, $container->get('computed'), 'container resolves factories once');
assert_same(43, $container->get('computed'), 'container caches factory result');

$provider = new RecordingProvider();
$runtime->options['sr_feature_flags'] = ['SR_PAID_DOWNLOADS_ENABLED' => true];
$plugin = new BootstrapPlugin($runtime, $checker, [$provider]);
$bootReport = $plugin->boot();
assert_true($bootReport->passed(), 'plugin boots when dependencies pass');
assert_true($provider->registered, 'plugin registers service providers after dependencies pass');
assert_true($plugin->container()->has('platform.features'), 'plugin registers feature flags in the container');
assert_same(true, $plugin->container()->get('recorded.feature.enabled'), 'provider receives platform feature flags');

$frontRuntime = new FakeRuntime(admin: false);
$blockedPlugin = new BootstrapPlugin($frontRuntime, $checker, [new RecordingProvider()]);
$blockedReport = $blockedPlugin->boot();
assert_false($blockedReport->passed(), 'plugin reports blocked state when dependencies fail');
assert_same([], $frontRuntime->adminNotices, 'frontend dependency failure degrades without rendering admin notices');

$adminRuntime = new FakeRuntime(admin: true);
$blockedAdminPlugin = new BootstrapPlugin($adminRuntime, $checker, [new RecordingProvider()]);
$blockedAdminPlugin->boot();
assert_same('admin_notices', $adminRuntime->actions[0]['hook'], 'admin dependency failure schedules an admin notice');
($adminRuntime->actions[0]['callback'])();
assert_same('error', $adminRuntime->adminNotices[0]['type'], 'admin dependency failure renders an error notice');
assert_true(
    str_contains($adminRuntime->adminNotices[0]['message'], 'Stock Resource platform is not active'),
    'admin dependency failure explains the platform is blocked instead of white-screening',
);

echo "sr-platform-bootstrap tests: ok\n";
