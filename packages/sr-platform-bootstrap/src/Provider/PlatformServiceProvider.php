<?php
declare(strict_types=1);

namespace StockResource\Platform\Provider;

use StockResource\Platform\Container\Container;
use StockResource\Platform\Feature\FeatureFlags;

final class PlatformServiceProvider implements ServiceProvider
{
    public function register(Container $container, FeatureFlags $features): void
    {
        $container->set('platform.ready', true);
        $container->set('platform.enabled_features', $features->all());
    }
}
