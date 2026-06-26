<?php
declare(strict_types=1);

if (! function_exists('sr_theme_archive_base_url')) {
    function sr_theme_archive_base_url(): string
    {
        $archive = function_exists('get_post_type_archive_link') ? get_post_type_archive_link('download') : '';

        if (is_string($archive) && $archive !== '') {
            return $archive;
        }

        return function_exists('home_url') ? home_url('/resources/') : '/resources/';
    }
}

if (! function_exists('sr_theme_archive_query_from_request')) {
    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    function sr_theme_archive_query_from_request(array $request): array
    {
        $allowed = ['category', 'content_type', 'indicator_type', 'page', 'per_page', 'platform', 'search', 'sort', 'strategy_tag'];
        $filters = ['category', 'content_type', 'indicator_type', 'platform', 'strategy_tag'];
        $params = [
            'page' => max(1, (int) ($request['page'] ?? 1)),
            'per_page' => min(48, max(1, (int) ($request['per_page'] ?? 12))),
            'sort' => trim((string) ($request['sort'] ?? 'updated_desc')),
        ];

        foreach ($request as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                return sr_theme_archive_invalid_query($key);
            }

            if (in_array($key, $filters, true)) {
                $slug = strtolower(trim((string) $value));
                if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                    return sr_theme_archive_invalid_query($key);
                }
                $params[$key] = $slug;
            }
        }

        if (! in_array($params['sort'], ['updated_desc', 'title_asc', 'popular_desc'], true)) {
            return sr_theme_archive_invalid_query('sort');
        }

        if (array_key_exists('search', $request)) {
            $search = preg_replace('/\s+/', ' ', trim((string) $request['search'])) ?? '';
            if ($search !== '') {
                $params['search'] = substr($search, 0, 100);
            }
        }

        ksort($params);

        return [
            'valid' => true,
            'params' => $params,
            'robots' => 'index,follow',
            'reset_url' => sr_theme_archive_base_url(),
            'canonical_url' => sr_theme_archive_canonical_url($params),
        ];
    }
}

if (! function_exists('sr_theme_archive_invalid_query')) {
    /**
     * @return array<string, mixed>
     */
    function sr_theme_archive_invalid_query(string $field): array
    {
        return [
            'valid' => false,
            'params' => ['page' => 1, 'per_page' => 12, 'sort' => 'updated_desc'],
            'robots' => 'noindex,follow',
            'error' => ['code' => 'sr_invalid_filter', 'field' => $field],
            'reset_url' => sr_theme_archive_base_url(),
            'canonical_url' => sr_theme_archive_base_url(),
        ];
    }
}

if (! function_exists('sr_theme_archive_canonical_query')) {
    /**
     * @param array<string, mixed> $params
     */
    function sr_theme_archive_canonical_query(array $params): string
    {
        ksort($params);

        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}

if (! function_exists('sr_theme_archive_canonical_url')) {
    /**
     * @param array<string, mixed> $params
     */
    function sr_theme_archive_canonical_url(array $params): string
    {
        return sr_theme_archive_base_url() . '?' . sr_theme_archive_canonical_query($params);
    }
}

if (! function_exists('sr_theme_archive_download_model')) {
    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    function sr_theme_archive_download_model(array $query): array
    {
        $resources = [
            [
                'title' => '通达信趋势观察指标',
                'excerpt' => '适合趋势识别和盘后复盘的公开资源。',
                'href' => sr_theme_archive_base_url() . 'tdx-trend/',
                'access_mode' => 'purchase_or_vip',
                'taxonomies' => ['platform' => 'tongdaxin', 'indicator_type' => 'sub-chart', 'content_type' => 'indicator'],
                'meta' => ['platform' => '通达信', 'indicator_type' => '副图', 'software_versions' => ['7.60']],
            ],
            [
                'title' => '量价突破选股模板',
                'excerpt' => '围绕量价关系构建的选股模板入口。',
                'href' => sr_theme_archive_base_url() . 'volume-breakout/',
                'access_mode' => 'purchase',
                'taxonomies' => ['platform' => 'tongdaxin', 'indicator_type' => 'stock-screening', 'content_type' => 'indicator'],
                'meta' => ['platform' => '通达信', 'indicator_type' => '选股', 'software_versions' => ['7.60']],
            ],
        ];

        /** @var array<string, mixed> $filtered */
        $filtered = apply_filters('sr_theme_archive_download_model', ['resources' => $resources], $query);
        $resources = is_array($filtered['resources'] ?? null) ? $filtered['resources'] : $resources;
        $resources = array_values(array_filter($resources, static fn(array $resource): bool => sr_theme_archive_resource_matches($resource, $query['params'] ?? [])));
        $total = count($resources);
        $page = (int) (($query['params']['page'] ?? 1));
        $perPage = (int) (($query['params']['per_page'] ?? 12));
        $paged = array_slice($resources, ($page - 1) * $perPage, $perPage);

        return [
            'resources' => $paged,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
            ],
            'vocabulary' => [
                'platform' => [['slug' => 'tongdaxin', 'name' => '通达信'], ['slug' => 'tonghuashun', 'name' => '同花顺']],
                'indicator_type' => [['slug' => 'sub-chart', 'name' => '副图'], ['slug' => 'stock-screening', 'name' => '选股']],
                'content_type' => [['slug' => 'indicator', 'name' => '指标'], ['slug' => 'tutorial', 'name' => '教程']],
            ],
        ];
    }
}

if (! function_exists('sr_theme_archive_resource_matches')) {
    /**
     * @param array<string, mixed> $resource
     * @param array<string, mixed> $params
     */
    function sr_theme_archive_resource_matches(array $resource, array $params): bool
    {
        $taxonomies = is_array($resource['taxonomies'] ?? null) ? $resource['taxonomies'] : [];
        foreach (['platform', 'indicator_type', 'content_type', 'strategy_tag', 'category'] as $key) {
            if (isset($params[$key]) && ($taxonomies[$key] ?? null) !== $params[$key]) {
                return false;
            }
        }

        if (isset($params['search'])) {
            $haystack = strtolower((string) ($resource['title'] ?? '') . ' ' . (string) ($resource['excerpt'] ?? ''));
            if (! str_contains($haystack, strtolower((string) $params['search']))) {
                return false;
            }
        }

        return true;
    }
}
