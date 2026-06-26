<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/components/helpers.php';
require_once dirname(__DIR__) . '/components/button.php';
require_once dirname(__DIR__) . '/components/notice.php';
require_once dirname(__DIR__) . '/components/resource-meta.php';

if (! function_exists('sr_theme_single_download_model')) {
    /**
     * @return array<string, mixed>
     */
    function sr_theme_single_download_model(): array
    {
        $model = [
            'title' => '通达信趋势观察指标',
            'excerpt' => '适合趋势识别和盘后复盘的公开资源。',
            'content' => '提供安装说明、使用场景和版本信息，下载内容以授权后展示为准。',
            'access_mode' => 'purchase_or_vip',
            'compatibility' => [
                'platform' => '通达信',
                'software_versions' => ['7.60'],
                'os' => 'Windows',
                'device' => 'desktop',
            ],
            'limitations' => ['仅用于辅助判断', '历史表现不代表未来结果'],
            'risk_notice' => '不构成投资建议，请结合自身风险承受能力使用。',
            'current_version' => [
                'version_label' => '1.2.0',
                'status' => 'active',
                'scan_status' => 'clean',
                'file_size' => 123456,
                'release_notes' => '优化趋势识别参数与示例说明。',
                'activated_at' => '2026-06-25T00:00:00Z',
            ],
            'access_decision' => [
                'allowed' => false,
                'cta' => 'PURCHASE',
                'label' => '获取资源',
                'href' => home_url('/checkout/'),
                'reason_code' => 'purchase_required',
            ],
        ];

        /** @var array<string, mixed> $filtered */
        $filtered = apply_filters('sr_theme_single_download_model', $model);

        return is_array($filtered) ? $filtered : $model;
    }
}

if (! function_exists('sr_theme_single_access_decision_presenter')) {
    /**
     * @param array<string, mixed> $decision
     */
    function sr_theme_single_access_decision_presenter(array $decision): string
    {
        $cta = in_array($decision['cta'] ?? 'UNAVAILABLE', ['DOWNLOAD', 'PURCHASE', 'LOGIN', 'JOIN_VIP', 'WAIT', 'UNAVAILABLE'], true)
            ? (string) $decision['cta']
            : 'UNAVAILABLE';
        $label = trim((string) ($decision['label'] ?? sr_theme_single_cta_label($cta)));
        $href = trim((string) ($decision['href'] ?? ''));
        $disabled = $cta === 'WAIT' || $cta === 'UNAVAILABLE' || $href === '';
        $attrs = [
            'class' => 'sr-button sr-button--primary sr-access-decision-presenter',
            'data-cta' => $cta,
            'aria-disabled' => $disabled ? 'true' : null,
        ];

        if (! $disabled) {
            $attrs['href'] = $href;

            return '<a' . sr_theme_attrs($attrs) . '>' . sr_theme_escape($label) . '</a>';
        }

        return '<span' . sr_theme_attrs($attrs) . '>' . sr_theme_escape($label) . '</span>';
    }
}

if (! function_exists('sr_theme_single_cta_label')) {
    function sr_theme_single_cta_label(string $cta): string
    {
        return match ($cta) {
            'DOWNLOAD' => '下载资源',
            'PURCHASE' => '获取资源',
            'LOGIN' => '登录后继续',
            'JOIN_VIP' => '查看会员方案',
            'WAIT' => '等待审核',
            default => '暂不可用',
        };
    }
}

if (! function_exists('sr_theme_single_list')) {
    /**
     * @param list<string> $items
     */
    function sr_theme_single_list(array $items): string
    {
        $html = '<ul>';
        foreach ($items as $item) {
            $html .= '<li>' . sr_theme_escape($item) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}

$resource = sr_theme_single_download_model();
$version = is_array($resource['current_version'] ?? null) ? $resource['current_version'] : [];
$compatibility = is_array($resource['compatibility'] ?? null) ? $resource['compatibility'] : [];
$limitations = is_array($resource['limitations'] ?? null) ? array_values(array_map('strval', $resource['limitations'])) : [];
$decision = is_array($resource['access_decision'] ?? null) ? $resource['access_decision'] : ['cta' => 'UNAVAILABLE'];

get_template_part('partials/header');
?>

<main class="sr-site-main" id="main">
    <article class="sr-single-download" aria-labelledby="sr-single-title">
        <header class="sr-single-header">
            <p class="sr-front-hero__eyebrow"><?php echo sr_theme_escape(sr_theme_access_label((string) ($resource['access_mode'] ?? 'unavailable'))); ?></p>
            <h1 id="sr-single-title"><?php echo sr_theme_escape($resource['title'] ?? ''); ?></h1>
            <p><?php echo sr_theme_escape($resource['excerpt'] ?? ''); ?></p>
            <div class="sr-single-cta">
                <?php echo sr_theme_single_access_decision_presenter($decision); ?>
            </div>
        </header>

        <section class="sr-single-content" aria-labelledby="sr-single-content-title">
            <h2 id="sr-single-content-title"><?php echo sr_theme_escape('资源说明'); ?></h2>
            <p><?php echo sr_theme_escape($resource['content'] ?? ''); ?></p>
        </section>

        <section class="sr-single-compatibility" aria-labelledby="sr-single-compatibility-title">
            <h2 id="sr-single-compatibility-title"><?php echo sr_theme_escape('兼容性'); ?></h2>
            <dl class="sr-resource-meta">
                <?php foreach ([
                    'platform' => '平台',
                    'software_versions' => '兼容版本',
                    'os' => '系统',
                    'device' => '设备',
                ] as $key => $label) : ?>
                    <div class="sr-resource-meta__item">
                        <dt class="sr-resource-meta__label"><?php echo sr_theme_escape($label); ?></dt>
                        <dd class="sr-resource-meta__value"><?php echo sr_theme_escape(sr_theme_unknown($compatibility[$key] ?? null)); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>

        <section class="sr-single-limitations" aria-labelledby="sr-single-limitations-title">
            <h2 id="sr-single-limitations-title"><?php echo sr_theme_escape('限制说明'); ?></h2>
            <?php echo $limitations === [] ? sr_theme_notice(['type' => 'empty', 'title' => '暂无限制说明']) : sr_theme_single_list($limitations); ?>
        </section>

        <section class="sr-single-version" aria-labelledby="sr-single-version-title">
            <h2 id="sr-single-version-title"><?php echo sr_theme_escape('当前版本'); ?></h2>
            <dl class="sr-resource-meta">
                <div class="sr-resource-meta__item">
                    <dt class="sr-resource-meta__label"><?php echo sr_theme_escape('版本号'); ?></dt>
                    <dd class="sr-resource-meta__value"><?php echo sr_theme_escape($version['version_label'] ?? '未核实'); ?></dd>
                </div>
                <div class="sr-resource-meta__item">
                    <dt class="sr-resource-meta__label"><?php echo sr_theme_escape('扫描状态'); ?></dt>
                    <dd class="sr-resource-meta__value"><?php echo sr_theme_escape($version['scan_status'] ?? '未核实'); ?></dd>
                </div>
                <div class="sr-resource-meta__item">
                    <dt class="sr-resource-meta__label"><?php echo sr_theme_escape('发布时间'); ?></dt>
                    <dd class="sr-resource-meta__value"><?php echo sr_theme_escape($version['activated_at'] ?? '未核实'); ?></dd>
                </div>
            </dl>
            <p><?php echo sr_theme_escape($version['release_notes'] ?? '暂无版本说明'); ?></p>
        </section>

        <section class="sr-single-risk" aria-labelledby="sr-single-risk-title">
            <h2 id="sr-single-risk-title"><?php echo sr_theme_escape('风险提示'); ?></h2>
            <?php echo sr_theme_notice(['type' => 'empty', 'title' => (string) ($resource['risk_notice'] ?? '请谨慎使用资源')]); ?>
        </section>
    </article>
</main>

<?php get_template_part('partials/footer'); ?>
