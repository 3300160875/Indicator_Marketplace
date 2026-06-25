<?php
declare(strict_types=1);

namespace StockResource\Platform\Dependency;

use StockResource\Platform\Runtime\Runtime;

final readonly class Requirement
{
    private function __construct(
        private string $kind,
        private string $minimumVersion,
        private ?string $pluginFile = null,
        private ?string $label = null,
    ) {
    }

    public static function php(string $minimumVersion): self
    {
        return new self('php', $minimumVersion, label: 'PHP');
    }

    public static function wordpress(string $minimumVersion): self
    {
        return new self('wordpress', $minimumVersion, label: 'WordPress');
    }

    public static function plugin(string $pluginFile, string $minimumVersion, string $label): self
    {
        return new self('plugin', $minimumVersion, $pluginFile, $label);
    }

    public function failure(Runtime $runtime): ?string
    {
        $actual = match ($this->kind) {
            'php' => $runtime->phpVersion(),
            'wordpress' => $runtime->wordpressVersion(),
            'plugin' => $runtime->pluginVersion((string) $this->pluginFile),
            default => null,
        };

        if ($actual === null || $actual === '') {
            return sprintf('%s is required at version %s or newer.', $this->label(), $this->minimumVersion);
        }

        if (! version_compare($actual, $this->minimumVersion, '>=')) {
            return sprintf('%s %s is below the required version %s.', $this->label(), $actual, $this->minimumVersion);
        }

        return null;
    }

    private function label(): string
    {
        return (string) $this->label;
    }
}
