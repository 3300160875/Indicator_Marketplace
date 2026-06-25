<?php
declare(strict_types=1);

namespace StockResource\Platform\Provider;

use StockResource\Platform\Container\Container;
use StockResource\Platform\Feature\FeatureFlags;

interface ServiceProvider
{
    public function register(Container $container, FeatureFlags $features): void;
}
