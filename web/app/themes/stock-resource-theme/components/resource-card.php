<?php
declare(strict_types=1);

require_once __DIR__ . '/button.php';
require_once __DIR__ . '/notice.php';
require_once __DIR__ . '/resource-meta.php';

if (! function_exists('sr_theme_status_badge')) {
    function sr_theme_status_badge(string $accessMode): string
    {
        $safeMode = in_array($accessMode, ['free', 'purchase', 'vip', 'purchase_or_vip', 'unavailable'], true)
            ? $accessMode
            : 'unavailable';

        return '<span class="sr-status-badge sr-status-badge--' . sr_theme_escape($safeMode) . '">' . sr_theme_escape(sr_theme_access_label($safeMode)) . '</span>';
    }
}

if (! function_exists('sr_theme_resource_card')) {
    /**
     * @param array<string, mixed> $resource
     */
    function sr_theme_resource_card(array $resource): string
    {
        $title = trim((string) ($resource['title'] ?? ''));
        $excerpt = trim((string) ($resource['excerpt'] ?? ''));
        $href = trim((string) ($resource['href'] ?? ''));
        $accessMode = (string) ($resource['access_mode'] ?? 'unavailable');
        $disabled = (bool) ($resource['disabled'] ?? false);
        $class = 'sr-resource-card' . ($disabled ? ' sr-resource-card--disabled' : '');

        $html = '<article class="' . sr_theme_escape($class) . '">';
        $html .= '<div class="sr-resource-card__header">';
        $html .= '<h3 class="sr-resource-card__title">';
        if ($href !== '' && ! $disabled) {
            $html .= '<a class="sr-resource-card__link" href="' . sr_theme_escape($href) . '">' . sr_theme_escape($title) . '</a>';
        } else {
            $html .= sr_theme_escape($title);
        }
        $html .= '</h3>';
        $html .= sr_theme_status_badge($disabled ? 'unavailable' : $accessMode);
        $html .= '</div>';

        if ($excerpt !== '') {
            $html .= '<p class="sr-resource-card__excerpt">' . sr_theme_escape($excerpt) . '</p>';
        } else {
            $html .= sr_theme_notice([
                'type' => 'empty',
                'title' => (string) ($resource['empty_message'] ?? '暂无资源说明'),
            ]);
        }

        $html .= sr_theme_resource_meta(is_array($resource['meta'] ?? null) ? $resource['meta'] : []);
        $html .= '</article>';

        return $html;
    }
}
