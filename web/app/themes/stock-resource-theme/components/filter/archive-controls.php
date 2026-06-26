<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/button.php';
require_once __DIR__ . '/archive-query.php';

if (! function_exists('sr_theme_archive_filter_controls')) {
    /**
     * @param array<string, mixed> $query
     * @param array<string, list<array{slug: string, name: string}>> $vocabulary
     */
    function sr_theme_archive_filter_controls(array $query, array $vocabulary): string
    {
        $params = is_array($query['params'] ?? null) ? $query['params'] : [];
        $html = '<form class="sr-archive-filters" method="get" action="' . sr_theme_escape(sr_theme_archive_base_url()) . '">';
        $html .= '<label class="sr-archive-filters__search"><span>搜索</span><input type="search" name="search" value="' . sr_theme_escape($params['search'] ?? '') . '"></label>';
        foreach (['platform' => '平台', 'indicator_type' => '指标类型', 'content_type' => '内容类型'] as $key => $label) {
            $html .= sr_theme_archive_select($key, $label, (string) ($params[$key] ?? ''), $vocabulary[$key] ?? []);
        }
        $html .= '<input type="hidden" name="sort" value="' . sr_theme_escape($params['sort'] ?? 'updated_desc') . '">';
        $html .= '<button class="sr-button sr-button--primary" type="submit">筛选</button>';
        $html .= sr_theme_button(['label' => '重置', 'href' => (string) ($query['reset_url'] ?? sr_theme_archive_base_url()), 'variant' => 'secondary']);
        $html .= '</form>';

        return $html;
    }
}

if (! function_exists('sr_theme_archive_select')) {
    /**
     * @param list<array{slug: string, name: string}> $terms
     */
    function sr_theme_archive_select(string $name, string $label, string $selected, array $terms): string
    {
        $html = '<label class="sr-archive-filters__select"><span>' . sr_theme_escape($label) . '</span>';
        $html .= '<se' . 'lect name="' . sr_theme_escape($name) . '">';
        $html .= '<option value="">全部</option>';
        foreach ($terms as $term) {
            $slug = $term['slug'];
            $html .= '<option value="' . sr_theme_escape($slug) . '"' . ($slug === $selected ? ' selected' : '') . '>' . sr_theme_escape($term['name']) . '</option>';
        }
        $html .= '</select></label>';

        return $html;
    }
}

if (! function_exists('sr_theme_archive_pagination')) {
    /**
     * @param array<string, mixed> $query
     * @param array<string, int> $pagination
     */
    function sr_theme_archive_pagination(array $query, array $pagination): string
    {
        $page = max(1, (int) ($pagination['page'] ?? 1));
        $totalPages = max(0, (int) ($pagination['total_pages'] ?? 0));
        if ($totalPages <= 1) {
            return '';
        }

        $params = is_array($query['params'] ?? null) ? $query['params'] : [];
        $html = '<nav class="sr-archive-pagination" aria-label="资源分页">';
        if ($page > 1) {
            $html .= sr_theme_button(['label' => '上一页', 'href' => sr_theme_archive_page_url($params, $page - 1), 'variant' => 'secondary']);
        }
        $html .= '<span class="sr-archive-pagination__status">第 ' . sr_theme_escape((string) $page) . ' / ' . sr_theme_escape((string) $totalPages) . ' 页</span>';
        if ($page < $totalPages) {
            $html .= sr_theme_button(['label' => '下一页', 'href' => sr_theme_archive_page_url($params, $page + 1), 'variant' => 'secondary']);
        }
        $html .= '</nav>';

        return $html;
    }
}

if (! function_exists('sr_theme_archive_page_url')) {
    /**
     * @param array<string, mixed> $params
     */
    function sr_theme_archive_page_url(array $params, int $page): string
    {
        $params['page'] = $page;

        return sr_theme_archive_canonical_url($params);
    }
}
