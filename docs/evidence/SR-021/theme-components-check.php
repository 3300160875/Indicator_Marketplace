<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3) . '/web/app/themes/stock-resource-theme';

function sr_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr_read(string $path): string
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Unable to read ' . $path);
    }

    return $contents;
}

$requiredFiles = [
    '/assets/css/tokens.css',
    '/assets/css/components.css',
    '/components/helpers.php',
    '/components/button.php',
    '/components/notice.php',
    '/components/resource-meta.php',
    '/components/resource-card.php',
];

foreach ($requiredFiles as $requiredFile) {
    sr_assert(is_file($root . $requiredFile), $requiredFile . ' must exist');
}

$themeCss = sr_read($root . '/assets/css/theme.css');
sr_assert(str_contains($themeCss, '@import url("./tokens.css");'), 'theme CSS imports design tokens');
sr_assert(str_contains($themeCss, '@import url("./components.css");'), 'theme CSS imports component styles');

$tokens = sr_read($root . '/assets/css/tokens.css');
foreach ([
    '--sr-color-text',
    '--sr-color-surface',
    '--sr-color-accent',
    '--sr-color-danger',
    '--sr-font-size-sm',
    '--sr-font-size-md',
    '--sr-font-size-lg',
    '--sr-space-1',
    '--sr-space-2',
    '--sr-space-4',
    '--sr-focus-ring',
] as $token) {
    sr_assert(str_contains($tokens, $token . ':'), 'token exists: ' . $token);
}
sr_assert(! preg_match('/font-size:\s*clamp\(/', $tokens), 'tokens do not scale font size with viewport width');

$componentsCss = sr_read($root . '/assets/css/components.css');
foreach ([
    '.sr-button',
    '.sr-button:focus-visible',
    '.sr-button[aria-disabled="true"]',
    '.sr-notice--empty',
    '.sr-notice--error',
    '.sr-resource-card',
    '.sr-resource-card--disabled',
    '.sr-resource-meta',
] as $selector) {
    sr_assert(str_contains($componentsCss, $selector), 'component CSS contains selector: ' . $selector);
}
sr_assert(str_contains($componentsCss, 'outline: var(--sr-focus-ring)'), 'component CSS uses tokenized focus ring');

require_once $root . '/components/button.php';
require_once $root . '/components/notice.php';
require_once $root . '/components/resource-meta.php';
require_once $root . '/components/resource-card.php';

sr_assert(function_exists('sr_theme_button'), 'button component function exists');
sr_assert(function_exists('sr_theme_notice'), 'notice component function exists');
sr_assert(function_exists('sr_theme_resource_meta'), 'resource meta component function exists');
sr_assert(function_exists('sr_theme_resource_card'), 'resource card component function exists');

$button = sr_theme_button(['label' => '生成下载链接', 'href' => '/download', 'variant' => 'primary']);
sr_assert(str_contains($button, 'class="sr-button sr-button--primary"'), 'button renders normal state');
sr_assert(str_contains($button, 'href="/download"'), 'button renders href');

$disabledButton = sr_theme_button(['label' => '暂不可用', 'disabled' => true]);
sr_assert(str_contains($disabledButton, 'aria-disabled="true"'), 'button renders disabled state');
sr_assert(! str_contains($disabledButton, 'href='), 'disabled button does not render href');

$emptyNotice = sr_theme_notice(['type' => 'empty', 'title' => '暂无资源']);
sr_assert(str_contains($emptyNotice, 'sr-notice--empty'), 'notice renders empty state');

$errorNotice = sr_theme_notice(['type' => 'error', 'title' => '加载失败']);
sr_assert(str_contains($errorNotice, 'role="alert"'), 'error notice renders alert role');
sr_assert(str_contains($errorNotice, 'sr-notice--error'), 'notice renders error state');

$meta = sr_theme_resource_meta([
    'platform' => '通达信',
    'indicator_type' => '副图',
    'software_versions' => ['7.60'],
    'source_included' => null,
]);
sr_assert(str_contains($meta, '未核实'), 'resource meta renders unknown values explicitly');

$card = sr_theme_resource_card([
    'title' => '通达信趋势指标',
    'excerpt' => '用于趋势观察。',
    'href' => '/resources/tdx-trend',
    'access_mode' => 'purchase',
    'meta' => ['platform' => '通达信', 'indicator_type' => '副图'],
]);
sr_assert(str_contains($card, 'sr-resource-card'), 'resource card renders base class');
sr_assert(str_contains($card, 'sr-status-badge'), 'resource card renders access badge');

$disabledCard = sr_theme_resource_card([
    'title' => '已下架资源',
    'excerpt' => '',
    'disabled' => true,
    'empty_message' => '资源已下架',
]);
sr_assert(str_contains($disabledCard, 'sr-resource-card--disabled'), 'resource card renders disabled state');
sr_assert(str_contains($disabledCard, '资源已下架'), 'resource card renders empty/disabled message');

echo "SR-021 theme components check: ok\n";
