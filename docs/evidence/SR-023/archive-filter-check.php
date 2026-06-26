<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$theme = $root . '/web/app/themes/stock-resource-theme';

function sr023_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

foreach ([
    '/templates/archive-download.php',
    '/components/filter/archive-query.php',
    '/components/filter/archive-controls.php',
] as $file) {
    sr023_assert(is_file($theme . $file), $file . ' must exist');
}

if (! function_exists('home_url')) {
    function home_url(string $path = '/'): string
    {
        return 'https://example.test' . $path;
    }
}
if (! function_exists('get_post_type_archive_link')) {
    function get_post_type_archive_link(string $postType): string
    {
        return 'https://example.test/resources/';
    }
}

require_once $theme . '/components/filter/archive-query.php';
require_once $theme . '/components/filter/archive-controls.php';

sr023_assert(function_exists('sr_theme_archive_query_from_request'), 'archive query builder exists');
sr023_assert(function_exists('sr_theme_archive_canonical_query'), 'canonical query builder exists');
sr023_assert(function_exists('sr_theme_archive_filter_controls'), 'filter controls renderer exists');

$query = sr_theme_archive_query_from_request([
    'search' => '  Alpha   Trend  ',
    'platform' => 'tongdaxin',
    'indicator_type' => 'sub-chart',
    'content_type' => 'indicator',
    'page' => '2',
]);
sr023_assert($query['valid'] === true, 'valid archive filters are accepted');
sr023_assert($query['params'] === [
    'content_type' => 'indicator',
    'indicator_type' => 'sub-chart',
    'page' => 2,
    'per_page' => 12,
    'platform' => 'tongdaxin',
    'search' => 'Alpha Trend',
    'sort' => 'updated_desc',
], 'archive query params are canonical');
sr023_assert(sr_theme_archive_canonical_query($query['params']) === 'content_type=indicator&indicator_type=sub-chart&page=2&per_page=12&platform=tongdaxin&search=Alpha%20Trend&sort=updated_desc', 'canonical query string is stable');

$invalid = sr_theme_archive_query_from_request(['platform' => '***', 'page' => '1']);
sr023_assert($invalid['valid'] === false, 'invalid filters are rejected');
sr023_assert($invalid['robots'] === 'noindex,follow', 'invalid filter combinations are noindex');
sr023_assert($invalid['reset_url'] === 'https://example.test/resources/', 'invalid filter state includes recoverable reset URL');

$controls = sr_theme_archive_filter_controls($query, [
    'platform' => [['slug' => 'tongdaxin', 'name' => '通达信']],
    'indicator_type' => [['slug' => 'sub-chart', 'name' => '副图']],
    'content_type' => [['slug' => 'indicator', 'name' => '指标']],
]);
sr023_assert(str_contains($controls, 'method="get"'), 'filters submit through URL query');
sr023_assert(str_contains($controls, 'name="platform"'), 'platform filter enters URL');
sr023_assert(str_contains($controls, 'name="search"'), 'search filter enters URL');
sr023_assert(str_contains($controls, 'href="https://example.test/resources/"'), 'filter controls render reset URL');

if (! function_exists('esc_html')) {
    function esc_html(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (! function_exists('esc_url')) {
    function esc_url(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (! function_exists('esc_attr')) {
    function esc_attr(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (! function_exists('esc_html_e')) {
    function esc_html_e(string $text, string $domain = ''): void
    {
        echo esc_html($text);
    }
}
if (! function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = ''): string
    {
        return esc_html($text);
    }
}
if (! function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}
if (! function_exists('bloginfo')) {
    function bloginfo(string $field): void
    {
        echo esc_html($field === 'description' ? '精选股票指标资源下载平台' : '指标资源平台');
    }
}
if (! function_exists('language_attributes')) {
    function language_attributes(): void
    {
        echo 'lang="zh-CN"';
    }
}
if (! function_exists('body_class')) {
    function body_class(): void
    {
        echo 'class="archive post-type-archive-download"';
    }
}
if (! function_exists('wp_head')) {
    function wp_head(): void
    {
    }
}
if (! function_exists('wp_footer')) {
    function wp_footer(): void
    {
    }
}
if (! function_exists('wp_body_open')) {
    function wp_body_open(): void
    {
    }
}
if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return $value;
    }
}
if (! function_exists('get_template_part')) {
    function get_template_part(string $slug): void
    {
        global $theme;
        require $theme . '/' . $slug . '.php';
    }
}

$_GET = ['platform' => '***'];
ob_start();
require $theme . '/templates/archive-download.php';
$invalidHtml = (string) ob_get_clean();
sr023_assert(str_contains($invalidHtml, 'name="robots" content="noindex,follow"'), 'invalid archive page renders noindex');
sr023_assert(str_contains($invalidHtml, 'sr-notice--error'), 'invalid archive page renders recoverable error state');

$_GET = ['platform' => 'unknown-platform'];
ob_start();
require $theme . '/templates/archive-download.php';
$emptyHtml = (string) ob_get_clean();
sr023_assert(str_contains($emptyHtml, 'sr-archive-empty'), 'archive page renders recoverable empty state');
sr023_assert(str_contains($emptyHtml, 'href="https://example.test/resources/"'), 'empty state links back to reset URL');

$_GET = ['platform' => 'tongdaxin', 'page' => '2', 'per_page' => '1'];
ob_start();
require $theme . '/templates/archive-download.php';
$archiveHtml = (string) ob_get_clean();
sr023_assert(str_contains($archiveHtml, 'rel="canonical"'), 'archive page emits canonical link');
sr023_assert(str_contains($archiveHtml, 'platform=tongdaxin'), 'archive canonical preserves valid URL filters');
sr023_assert(str_contains($archiveHtml, 'sr-archive-pagination'), 'archive page renders pagination');
sr023_assert(str_contains($archiveHtml, 'sr-resource-card'), 'archive page renders resource cards');

echo "SR-023 archive filter check passed.\n";
