<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if (! function_exists('sr_theme_resource_meta')) {
    /**
     * @param array<string, mixed> $meta
     */
    function sr_theme_resource_meta(array $meta): string
    {
        $items = [
            'platform' => '平台',
            'indicator_type' => '类型',
            'software_versions' => '兼容版本',
            'source_included' => '源码',
            'future_function_status' => '未来函数',
        ];

        $html = '<dl class="sr-resource-meta">';
        foreach ($items as $key => $label) {
            $html .= '<div class="sr-resource-meta__item">';
            $html .= '<dt class="sr-resource-meta__label">' . sr_theme_escape($label) . '</dt>';
            $html .= '<dd class="sr-resource-meta__value">' . sr_theme_escape(sr_theme_unknown($meta[$key] ?? null)) . '</dd>';
            $html .= '</div>';
        }
        $html .= '</dl>';

        return $html;
    }
}
