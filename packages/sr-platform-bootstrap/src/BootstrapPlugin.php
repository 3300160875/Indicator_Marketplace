<?php
declare(strict_types=1);

namespace StockResource\Platform;

use StockResource\Platform\Admin\AdminNoticeRenderer;
use StockResource\Platform\Container\Container;
use StockResource\Platform\Dependency\DependencyChecker;
use StockResource\Platform\Dependency\DependencyReport;
use StockResource\Platform\Feature\FeatureFlags;
use StockResource\Platform\Provider\ServiceProvider;
use StockResource\Platform\Runtime\Runtime;

final class BootstrapPlugin
{
    private Container $container;

    private ?DependencyReport $report = null;

    /**
     * @param list<ServiceProvider> $providers
     */
    public function __construct(
        private readonly Runtime $runtime,
        private readonly DependencyChecker $dependencies,
        private readonly array $providers,
    ) {
        $this->container = new Container();
    }

    public function boot(): DependencyReport
    {
        if ($this->report !== null) {
            return $this->report;
        }

        $this->report = $this->dependencies->check($this->runtime);

        if (! $this->report->passed()) {
            if ($this->runtime->isAdmin()) {
                $renderer = new AdminNoticeRenderer($this->runtime);
                $report = $this->report;
                $this->runtime->addAction('admin_notices', static fn() => $renderer->render($report));
            }

            return $this->report;
        }

        $features = FeatureFlags::fromOptions($this->runtime->option('sr_feature_flags', []));
        $this->container->set('platform.runtime', $this->runtime);
        $this->container->set('platform.dependencies', $this->report);
        $this->container->set('platform.features', $features);

        foreach ($this->providers as $provider) {
            $provider->register($this->container, $features);
        }

        return $this->report;
    }

    public function container(): Container
    {
        return $this->container;
    }
}
