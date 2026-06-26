<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$theme = $root . '/web/app/themes/stock-resource-theme';

function sr024_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

sr024_assert(is_file($theme . '/templates/single-download.php'), 'single download template must exist');

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
if (! function_exists('get_permalink')) {
    function get_permalink(int $post = 0): string
    {
        return 'https://example.test/resources/tdx-trend/';
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
        echo 'class="single single-download"';
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
if (! function_exists('get_template_part')) {
    function get_template_part(string $slug): void
    {
        global $theme;
        require $theme . '/' . $slug . '.php';
    }
}
if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if ($hook !== 'sr_theme_single_download_model') {
            return $value;
        }

        $value['current_version']['storage_key'] = 'private/object-key.zip';
        $value['current_version']['sha256'] = str_repeat('a', 64);
        $value['current_version']['internal_notes'] = 'do not expose';
        $value['hidden_content'] = 'secret download instructions';

        return $value;
    }
}

ob_start();
require $theme . '/templates/single-download.php';
$html = (string) ob_get_clean();

foreach ([
    'sr-single-download',
    'sr-single-compatibility',
    'sr-single-limitations',
    'sr-single-version',
    'sr-single-risk',
    'sr-single-cta',
    'sr-access-decision-presenter',
] as $needle) {
    sr024_assert(str_contains($html, $needle), 'single resource page contains ' . $needle);
}

foreach (['通达信', 'Windows', '1.2.0', '仅用于辅助判断', '不构成投资建议'] as $needle) {
    sr024_assert(str_contains($html, $needle), 'single resource page renders visible detail: ' . $needle);
}

foreach (['private/object-key.zip', str_repeat('a', 64), 'internal_notes', 'secret download instructions'] as $leak) {
    sr024_assert(! str_contains($html, $leak), 'single resource page must not leak ' . $leak);
}

sr024_assert(str_contains($html, 'data-cta="PURCHASE"'), 'CTA is provided by access decision presenter');
sr024_assert(str_contains($html, 'href="https://example.test/checkout/"'), 'CTA presenter renders action URL');

echo "SR-024 single download check passed.\n";
