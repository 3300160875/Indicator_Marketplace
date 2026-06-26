<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$theme = $root.'/web/app/themes/stock-resource-theme';

function sr025_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

sr025_assert(is_file($theme.'/templates/page-vip.php'), 'VIP page template must exist');

if (! function_exists('home_url')) {
    function home_url(string $path = '/'): string
    {
        return 'https://example.test'.$path;
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
        echo htmlspecialchars($field === 'description' ? '精选股票指标资源下载平台' : '指标资源平台', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
        echo 'class="page page-vip"';
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
        global $sr025Scenario;

        if ($hook !== 'sr_theme_vip_page_model') {
            return $value;
        }

        if (is_string($sr025Scenario ?? null) && $sr025Scenario !== 'ready') {
            $value['status'] = $sr025Scenario;
            $value['payment_enabled'] = false;
            $value['plans'] = [];

            return $value;
        }

        $value['payment_enabled'] = false;
        $value['plans'] = [
            [
                'id' => 'vip-monthly',
                'name' => '月度 VIP',
                'price_label' => '¥39/月',
                'price_source' => 'edd',
                'quota_label' => '每月 20 次资源下载',
                'scope' => ['VIP 包含资源', '版本更新期内资源'],
                'exclusions' => ['单独购买专属资源', '已下架资源'],
                'cta' => ['label' => '支付暂未启用', 'href' => 'https://example.test/checkout/?plan=vip-monthly'],
            ],
            [
                'id' => 'vip-yearly',
                'name' => '年度 VIP',
                'price_label' => '¥399/年',
                'price_source' => 'edd',
                'quota_label' => '每月 80 次资源下载',
                'scope' => ['VIP 包含资源', '年度更新权益'],
                'exclusions' => ['定制开发', '收益类承诺'],
                'cta' => ['label' => '支付暂未启用', 'href' => 'https://example.test/checkout/?plan=vip-yearly'],
            ],
        ];

        return $value;
    }
}

function sr025_render(string $scenario = 'ready'): string
{
    global $theme, $sr025Scenario;

    $sr025Scenario = $scenario;

    ob_start();
    require $theme.'/templates/page-vip.php';

    return (string) ob_get_clean();
}

$html = sr025_render();

foreach ([
    'sr-vip-page',
    'sr-vip-hero',
    'sr-vip-plans',
    'sr-vip-plan',
    'sr-vip-plan__price',
    'sr-vip-plan__quota',
    'sr-vip-plan__scope',
    'sr-vip-plan__exclusions',
] as $needle) {
    sr025_assert(str_contains($html, $needle), 'VIP page contains '.$needle);
}

foreach (['月度 VIP', '年度 VIP', '¥39/月', '¥399/年', '每月 20 次资源下载', '每月 80 次资源下载', '单独购买专属资源', '定制开发'] as $needle) {
    sr025_assert(str_contains($html, $needle), 'VIP page renders transparent plan data: '.$needle);
}

sr025_assert(str_contains($html, 'data-price-source="edd"'), 'VIP page marks prices as EDD sourced');
sr025_assert(str_contains($html, 'data-payment-enabled="false"'), 'VIP page exposes disabled payment state');
sr025_assert(str_contains($html, 'aria-disabled="true"'), 'disabled payment renders non-clickable CTA');
sr025_assert(! str_contains($html, 'href="https://example.test/checkout/?plan=vip-monthly"'), 'disabled payment does not render checkout href');

foreach (['保证收益', '稳赚', '预期收益', '实盘收益承诺'] as $forbidden) {
    sr025_assert(! str_contains($html, $forbidden), 'VIP page avoids forbidden earnings claim: '.$forbidden);
}

foreach ([
    'loading' => '套餐加载中',
    'empty' => '暂无可展示套餐',
    'error' => '套餐加载失败',
    'restricted' => '暂无权限查看套餐',
] as $scenario => $needle) {
    $scenarioHtml = sr025_render($scenario);
    sr025_assert(str_contains($scenarioHtml, $needle), 'VIP page covers '.$scenario.' state');
    sr025_assert(! str_contains($scenarioHtml, 'href="https://example.test/checkout/'), $scenario.' state does not render checkout href');
}

echo "SR-025 VIP page check passed.\n";
