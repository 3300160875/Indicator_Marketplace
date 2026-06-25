<?php
declare(strict_types=1);

namespace StockResource\Core\Content\Meta;

use RuntimeException;

final readonly class DownloadMetaCatalog
{
    /** @param array<string, DownloadMetaDefinition> $definitions */
    private function __construct(private array $definitions)
    {
    }

    public static function defaults(): self
    {
        $definitions = [
            new DownloadMetaDefinition('_sr_product_type', 'enum', 'resource', false, ['resource', 'membership_plan']),
            new DownloadMetaDefinition('_sr_access_mode', 'enum', 'unavailable', true, ['free', 'purchase', 'vip', 'purchase_or_vip', 'unavailable']),
            new DownloadMetaDefinition('_sr_software_versions', 'json_array', [], true),
            new DownloadMetaDefinition('_sr_device', 'enum', 'unknown', true, ['desktop', 'mobile', 'both', 'unknown']),
            new DownloadMetaDefinition('_sr_os', 'enum', 'unknown', true, ['windows', 'macos', 'mobile', 'multiple', 'unknown']),
            new DownloadMetaDefinition('_sr_file_format', 'enum', 'other', true, ['tn6', 'tni', 'tne', 'txt', 'zip', 'other']),
            new DownloadMetaDefinition('_sr_charset', 'enum', 'unknown', true, ['utf8', 'gbk', 'other', 'unknown']),
            new DownloadMetaDefinition('_sr_source_included', 'enum', 'unknown', true, ['yes', 'no', 'unknown']),
            new DownloadMetaDefinition('_sr_future_function_status', 'enum', 'unknown', true, ['none', 'contains', 'unknown', 'na']),
            new DownloadMetaDefinition('_sr_l2_required', 'enum', 'unknown', true, ['yes', 'no', 'unknown']),
            new DownloadMetaDefinition('_sr_parameters_json', 'json_object', [], true),
            new DownloadMetaDefinition('_sr_install_steps', 'html', '', true),
            new DownloadMetaDefinition('_sr_usage_scenarios', 'html', '', true),
            new DownloadMetaDefinition('_sr_limitations', 'html', '', true),
            new DownloadMetaDefinition('_sr_faq_json', 'json_array', [], true),
            new DownloadMetaDefinition('_sr_current_version_id', 'bigint', null, true, [], true),
            new DownloadMetaDefinition('_sr_rights_status', 'enum', 'pending', false, ['pending', 'approved', 'rejected', 'expired']),
            new DownloadMetaDefinition('_sr_rights_record_id', 'bigint', null, false, [], true),
            new DownloadMetaDefinition('_sr_risk_level', 'enum', 'medium', false, ['low', 'medium', 'high', 'blocked']),
            new DownloadMetaDefinition('_sr_disclaimer_version', 'string', '', true),
            new DownloadMetaDefinition('_sr_featured', 'bool', false, false),
            new DownloadMetaDefinition('_sr_sort_weight', 'int', 0, false),
            new DownloadMetaDefinition('_sr_related_resource_ids', 'json_array', [], true),
        ];

        $byKey = [];
        foreach ($definitions as $definition) {
            $byKey[$definition->key()] = $definition;
        }

        return new self($byKey);
    }

    /**
     * @return array<string, DownloadMetaDefinition>
     */
    public function definitions(): array
    {
        return $this->definitions;
    }

    /**
     * @return list<DownloadMetaDefinition>
     */
    public function publicDefinitions(): array
    {
        return array_values(array_filter(
            $this->definitions,
            static fn(DownloadMetaDefinition $definition): bool => $definition->public(),
        ));
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): DownloadMetaDefinition
    {
        return $this->definitions[$key] ?? throw new RuntimeException('Unknown download meta key: ' . $key);
    }
}
