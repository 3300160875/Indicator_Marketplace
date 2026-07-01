<?php
declare(strict_types=1);

namespace StockResource\PrivateDownloads;

use StockResource\Entitlements\Application\EntitlementService;
use StockResource\Entitlements\Application\InMemoryQuotaCounterStore;
use StockResource\Entitlements\Application\QuotaService;
use StockResource\Entitlements\Infrastructure\Repository\InMemoryEntitlementRepository;
use StockResource\PrivateDownloads\Rest\CreateDownloadTokenController;
use StockResource\PrivateDownloads\Rest\CreateDownloadTokenRouteRegistrar;
use StockResource\PrivateDownloads\Rest\EntitlementServiceAccessDecisionGateway;
use StockResource\PrivateDownloads\Rest\InMemoryCreateDownloadTokenIdempotencyStore;
use StockResource\PrivateDownloads\Rest\QuotaServiceReservationGateway;
use StockResource\PrivateDownloads\Rest\RecordingTransactionRunner;
use StockResource\PrivateDownloads\Token\DownloadTokenService;
use StockResource\PrivateDownloads\Token\InMemoryDownloadTokenRepository;

final class Plugin
{
    private const SLUG = 'sr-private-downloads';
    private const VERSION = '0.1.0';
    private const REQUIRED_PLUGINS = ['easy-digital-downloads/easy-digital-downloads.php'];
    private const REQUIRED_CLASSES = ['StockResource\\Platform\\BootstrapPlugin'];

    public static function slug(): string
    {
        return self::SLUG;
    }

    public static function version(): string
    {
        return self::VERSION;
    }

    /**
     * @return list<string>
     */
    public static function requiredPlugins(): array
    {
        return self::REQUIRED_PLUGINS;
    }

    /**
     * @return list<string>
     */
    public static function requiredClasses(): array
    {
        return self::REQUIRED_CLASSES;
    }

    /**
     * @param null|callable(string): bool $pluginActive
     * @param null|callable(string): bool $classExists
     * @return list<string>
     */
    public static function missingRuntimeDependencies(?callable $pluginActive = null, ?callable $classExists = null): array
    {
        $pluginActive ??= static fn(string $plugin): bool => ! function_exists('is_plugin_active') || is_plugin_active($plugin);
        $classExists ??= static fn(string $class): bool => class_exists($class);

        $missing = [];
        foreach (self::REQUIRED_PLUGINS as $plugin) {
            if (! $pluginActive($plugin)) {
                $missing[] = 'plugin:' . $plugin;
            }
        }
        foreach (self::REQUIRED_CLASSES as $class) {
            if (! $classExists($class)) {
                $missing[] = 'class:' . $class;
            }
        }

        return $missing;
    }

    public static function boot(): bool
    {
        if (self::missingRuntimeDependencies() !== []) {
            return false;
        }

        if (function_exists('add_action')) {
            add_action('rest_api_init', static function (): void {
                require_once dirname(__DIR__, 2) . '/sr-contracts/src/Entitlement/AccessDecision.php';
                require_once dirname(__DIR__, 2) . '/sr-contracts/src/Entitlement/AccessDecisionContext.php';
                require_once dirname(__DIR__, 2) . '/sr-entitlements/src/Application/QuotaService.php';
                require_once __DIR__ . '/Token/DownloadTokenService.php';
                require_once __DIR__ . '/Rest/CreateDownloadTokenController.php';

                $appKey = function_exists('wp_salt') ? wp_salt('auth') : 'local-runtime-download-token-key';
                (new CreateDownloadTokenRouteRegistrar(new CreateDownloadTokenController(
                    new EntitlementServiceAccessDecisionGateway(new EntitlementService(new InMemoryEntitlementRepository())),
                    new QuotaServiceReservationGateway(new QuotaService(new InMemoryQuotaCounterStore())),
                    new DownloadTokenService(new InMemoryDownloadTokenRepository(), $appKey),
                    new InMemoryCreateDownloadTokenIdempotencyStore(),
                    new RecordingTransactionRunner(),
                )))->register();
            });
        }

        return true;
    }
}
