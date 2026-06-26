<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$theme = $root.'/web/app/themes/stock-resource-theme';
$template = $theme.'/templates/account/page-account.php';

function sr026_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

sr026_assert(is_file($template), 'account page template must exist');

if (! function_exists('home_url')) {
    function home_url(string $path = '/'): string
    {
        return 'https://example.test'.$path;
    }
}
if (! function_exists('wp_login_url')) {
    function wp_login_url(string $redirect = ''): string
    {
        return 'https://example.test/login/?redirect_to='.rawurlencode($redirect);
    }
}
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
        echo 'class="page page-account"';
    }
}
if (! function_exists('wp_head')) {
    function wp_head(): void {}
}
if (! function_exists('wp_footer')) {
    function wp_footer(): void {}
}
if (! function_exists('wp_body_open')) {
    function wp_body_open(): void {}
}
if (! function_exists('get_template_part')) {
    function get_template_part(string $slug): void
    {
        global $theme;
        require $theme.'/'.$slug.'.php';
    }
}
if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        global $sr026Scenario;

        if ($hook !== 'sr_theme_account_page_model') {
            return $value;
        }

        $value['status'] = 'ready';
        $value['is_logged_in'] = true;
        $value['owner_verified'] = true;
        $value['user'] = [
            'display_name' => '测试用户',
            'email_label' => 'u***@example.test',
        ];
        $value['sections']['orders'] = [
            'state' => 'ready',
            'title' => '订单中心',
            'items' => [
                ['id' => 'order-1001', 'title' => '通达信趋势观察指标', 'meta' => '已完成 · 2026-06-26'],
            ],
        ];
        $value['sections']['downloads'] = [
            'state' => 'ready',
            'title' => '下载中心',
            'items' => [
                ['id' => 'download-1001', 'title' => '通达信趋势观察指标 v1.2.0', 'meta' => '授权有效'],
            ],
        ];

        return match ($sr026Scenario ?? 'ready') {
            'logged_out' => array_replace($value, ['is_logged_in' => false, 'owner_verified' => false]),
            'restricted' => array_replace($value, ['owner_verified' => false]),
            'loading' => array_replace($value, ['status' => 'loading']),
            'error' => array_replace($value, ['status' => 'error']),
            'empty' => array_replace_recursive($value, [
                'sections' => [
                    'orders' => ['state' => 'empty', 'items' => []],
                    'downloads' => ['state' => 'empty', 'items' => []],
                ],
            ]),
            default => $value,
        };
    }
}

function sr026_render(string $scenario = 'ready'): string
{
    global $sr026Scenario, $template;

    $sr026Scenario = $scenario;

    ob_start();
    require $template;

    return (string) ob_get_clean();
}

$html = sr026_render();

foreach ([
    'sr-account-shell',
    'sr-account-nav',
    'sr-account-section--orders',
    'sr-account-section--downloads',
    'data-authenticated="true"',
    'data-owner-verified="true"',
    '测试用户',
    '订单中心',
    '下载中心',
    '通达信趋势观察指标',
] as $needle) {
    sr026_assert(str_contains($html, $needle), 'account shell renders '.$needle);
}

$loggedOut = sr026_render('logged_out');
sr026_assert(str_contains($loggedOut, 'data-authenticated="false"'), 'logged out state exposes authentication gate');
sr026_assert(str_contains($loggedOut, '登录后查看用户中心'), 'logged out state asks user to log in');
sr026_assert(! str_contains($loggedOut, 'order-1001'), 'logged out state does not render orders');
sr026_assert(! str_contains($loggedOut, 'download-1001'), 'logged out state does not render downloads');

$restricted = sr026_render('restricted');
sr026_assert(str_contains($restricted, 'data-owner-verified="false"'), 'restricted state exposes ownership gate');
sr026_assert(str_contains($restricted, '当前账号无权查看此用户中心'), 'restricted state blocks cross-account access');
sr026_assert(! str_contains($restricted, 'order-1001'), 'restricted state does not render orders');

foreach ([
    'loading' => '用户中心加载中',
    'error' => '用户中心暂不可用',
    'empty' => '暂无订单记录',
] as $scenario => $needle) {
    sr026_assert(str_contains(sr026_render($scenario), $needle), 'account shell covers '.$scenario.' state');
}

$source = '';
foreach (glob($theme.'/templates/account/*.php') ?: [] as $file) {
    $source .= (string) file_get_contents($file)."\n";
}
foreach (['wpdb->', 'edd_orders', 'edd_customers', 'edd_order_items', 'SELECT '] as $forbidden) {
    sr026_assert(! str_contains($source, $forbidden), 'account shell does not directly access EDD/internal tables: '.$forbidden);
}

echo "SR-026 account shell check passed.\n";
