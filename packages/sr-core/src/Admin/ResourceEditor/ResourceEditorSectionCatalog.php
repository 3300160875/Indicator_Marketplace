<?php
declare(strict_types=1);

namespace StockResource\Core\Admin\ResourceEditor;

use RuntimeException;

final readonly class ResourceEditorSectionCatalog
{
    /** @param array<string, EditorSection> $sections */
    private function __construct(private array $sections)
    {
    }

    public static function defaults(): self
    {
        $sections = [
            new EditorSection('editorial', '编辑', [
                'post_title',
                'post_excerpt',
                'post_content',
                'download_category',
                'sr_platform',
                'sr_indicator_type',
                'sr_strategy_tag',
                'sr_content_type',
                '_sr_install_steps',
                '_sr_usage_scenarios',
                '_sr_limitations',
                '_sr_faq_json',
            ]),
            new EditorSection('technical', '技术', [
                '_sr_software_versions',
                '_sr_device',
                '_sr_os',
                '_sr_file_format',
                '_sr_charset',
                '_sr_source_included',
                '_sr_future_function_status',
                '_sr_l2_required',
                '_sr_parameters_json',
                '_sr_current_version_id',
            ]),
            new EditorSection('rights', '版权', [
                '_sr_rights_status',
                '_sr_rights_record_id',
                '_sr_risk_level',
                '_sr_disclaimer_version',
            ]),
            new EditorSection('commercial', '商业', [
                '_sr_access_mode',
            ]),
            new EditorSection('operations', '运营', [
                '_sr_featured',
                '_sr_sort_weight',
                '_sr_related_resource_ids',
            ]),
        ];

        $byKey = [];
        foreach ($sections as $section) {
            $byKey[$section->key()] = $section;
        }

        return new self($byKey);
    }

    /**
     * @return array<string, EditorSection>
     */
    public function sections(): array
    {
        return $this->sections;
    }

    public function get(string $key): EditorSection
    {
        return $this->sections[$key] ?? throw new RuntimeException('Unknown resource editor section: ' . $key);
    }

    public function sectionFor(string $field): ?EditorSection
    {
        foreach ($this->sections as $section) {
            if (in_array($field, $section->fields(), true)) {
                return $section;
            }
        }

        return null;
    }
}
