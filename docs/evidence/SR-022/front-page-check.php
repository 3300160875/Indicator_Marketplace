<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$theme = $root . '/web/app/themes/stock-resource-theme';

function sr022_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr022_read(string $path): string
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Unable to read ' . $path);
    }

    return $contents;
}

foreach ([
    '/templates/front-page.php',
    '/partials/header.php',
    '/partials/footer.php',
    '/partials/front-page-data.php',
    '/partials/front-page-sections.php',
] as $file) {
    sr022_assert(is_file($theme . $file), $file . ' must exist');
}

$frontPage = sr022_read($theme . '/templates/front-page.php');
$header = sr022_read($theme . '/partials/header.php');
$footer = sr022_read($theme . '/partials/footer.php');
$frontData = sr022_read($theme . '/partials/front-page-data.php');

foreach ([$frontPage, $header, $footer, $frontData] as $contents) {
    sr022_assert(! preg_match('/(￥|¥|price|quota|entitlement)/i', $contents), 'front page/navigation/footer must not hard-code price or entitlement rules');
    sr022_assert(! preg_match('/wpdb\\s*->|SELECT\\s+/i', $contents), 'front page files must not query databases directly');
}

sr022_assert(str_contains($frontData, 'apply_filters'), 'front page content must be injectable from service layer/filter');
sr022_assert(str_contains($frontPage, 'sr_theme_front_page_model'), 'front page template consumes the page model');
sr022_assert(str_contains($header, 'aria-label'), 'header navigation exposes accessible labels');
sr022_assert(str_contains($footer, 'sr-footer-nav'), 'footer renders a structured footer navigation');

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
        echo 'class="home"';
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
    function apply_filters(string $hook, mixed $value): mixed
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

ob_start();
require $theme . '/templates/front-page.php';
$html = (string) ob_get_clean();

foreach ([
    'sr-site-header',
    'sr-primary-nav',
    'sr-front-hero',
    'sr-front-topics',
    'sr-front-featured',
    'sr-front-empty',
    'sr-site-footer',
] as $needle) {
    sr022_assert(str_contains($html, $needle), 'rendered front page contains ' . $needle);
}

sr022_assert(substr_count($html, 'sr-resource-card') >= 2, 'front page renders resource cards from model data');
sr022_assert(str_contains($html, 'aria-label="主导航"'), 'header primary navigation has an accessible label');
sr022_assert(str_contains($html, 'aria-label="页脚导航"'), 'footer navigation has an accessible label');
sr022_assert(! preg_match('/(￥|¥|\\$)/', $html), 'rendered front page does not expose hard-coded prices');

echo "SR-022 front page check passed.\n";
