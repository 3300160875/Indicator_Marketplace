<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/components/helpers.php';
require_once dirname(__DIR__).'/components/button.php';
require_once dirname(__DIR__).'/components/notice.php';

if (! function_exists('sr_theme_vip_page_model')) {
    /**
     * @return array<string, mixed>
     */
    function sr_theme_vip_page_model(): array
    {
        $model = [
            'payment_enabled' => false,
            'status' => 'ready',
            'hero' => [
                'eyebrow' => 'VIP 资源服务',
                'title' => 'VIP 套餐对比',
                'body' => '清楚展示会员范围、排除项和配额；实际价格由 EDD 服务注入。',
            ],
            'plans' => [
                [
                    'id' => 'vip-standard',
                    'name' => 'VIP 标准版',
                    'price_label' => '等待 EDD 价格',
                    'price_source' => 'edd',
                    'quota_label' => '配额待服务层注入',
                    'scope' => ['VIP 包含资源', '版本更新期内资源'],
                    'exclusions' => ['单独购买专属资源', '已下架资源', '定制开发'],
                    'cta' => ['label' => '支付暂未启用', 'href' => home_url('/checkout/')],
                ],
            ],
        ];

        /** @var array<string, mixed> $filtered */
        $filtered = apply_filters('sr_theme_vip_page_model', $model);

        return is_array($filtered) ? $filtered : $model;
    }
}

if (! function_exists('sr_theme_vip_list')) {
    /**
     * @param  list<string>  $items
     */
    function sr_theme_vip_list(array $items, string $className): string
    {
        if ($items === []) {
            return '<p class="'.sr_theme_escape($className).'">'.sr_theme_escape('未配置').'</p>';
        }

        $html = '<ul class="'.sr_theme_escape($className).'">';
        foreach ($items as $item) {
            $html .= '<li>'.sr_theme_escape($item).'</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}

if (! function_exists('sr_theme_vip_plan_cta')) {
    /**
     * @param  array<string, mixed>  $plan
     */
    function sr_theme_vip_plan_cta(array $plan, bool $paymentEnabled): string
    {
        $cta = is_array($plan['cta'] ?? null) ? $plan['cta'] : [];
        $label = trim((string) ($cta['label'] ?? ($paymentEnabled ? '选择套餐' : '支付暂未启用')));
        $href = trim((string) ($cta['href'] ?? ''));

        return sr_theme_button([
            'label' => $label,
            'href' => $href,
            'variant' => 'primary',
            'disabled' => ! $paymentEnabled,
        ]);
    }
}

$vipModel = sr_theme_vip_page_model();
$hero = is_array($vipModel['hero'] ?? null) ? $vipModel['hero'] : [];
$plans = is_array($vipModel['plans'] ?? null) ? array_values(array_filter($vipModel['plans'], 'is_array')) : [];
$paymentEnabled = (bool) ($vipModel['payment_enabled'] ?? false);
$status = in_array($vipModel['status'] ?? 'ready', ['ready', 'loading', 'empty', 'error', 'restricted'], true)
    ? (string) ($vipModel['status'] ?? 'ready')
    : 'ready';

get_template_part('partials/header');
?>

<main class="sr-site-main" id="main">
    <article class="sr-vip-page" aria-labelledby="sr-vip-title" data-payment-enabled="<?php echo $paymentEnabled ? 'true' : 'false'; ?>">
        <header class="sr-vip-hero">
            <p class="sr-front-hero__eyebrow"><?php echo sr_theme_escape($hero['eyebrow'] ?? 'VIP 资源服务'); ?></p>
            <h1 id="sr-vip-title"><?php echo sr_theme_escape($hero['title'] ?? 'VIP 套餐对比'); ?></h1>
            <p><?php echo sr_theme_escape($hero['body'] ?? ''); ?></p>
        </header>

        <?php if ($status === 'error') { ?>
            <?php echo sr_theme_notice(['type' => 'error', 'title' => '套餐加载失败', 'body' => '请稍后重试，支付入口不会在错误状态下开放。']); ?>
        <?php } elseif ($status === 'loading') { ?>
            <?php echo sr_theme_notice(['type' => 'empty', 'title' => '套餐加载中', 'body' => '正在等待服务层返回 EDD 价格和套餐范围。']); ?>
        <?php } elseif ($status === 'restricted') { ?>
            <?php echo sr_theme_notice(['type' => 'empty', 'title' => '暂无权限查看套餐', 'body' => '当前账号暂不能查看 VIP 套餐，购买入口不会开放。']); ?>
        <?php } elseif ($status === 'empty' || $plans === []) { ?>
            <?php echo sr_theme_notice(['type' => 'empty', 'title' => '暂无可展示套餐', 'body' => '套餐未配置前不会展示购买入口。']); ?>
        <?php } else { ?>
            <section class="sr-vip-plans" aria-labelledby="sr-vip-plans-title">
                <h2 id="sr-vip-plans-title"><?php echo sr_theme_escape('套餐规则与范围'); ?></h2>

                <?php foreach ($plans as $plan) { ?>
                    <?php
                    $scope = is_array($plan['scope'] ?? null) ? array_values(array_map('strval', $plan['scope'])) : [];
                    $exclusions = is_array($plan['exclusions'] ?? null) ? array_values(array_map('strval', $plan['exclusions'])) : [];
                    $priceSource = (string) ($plan['price_source'] ?? 'edd');
                    ?>
                    <section class="sr-vip-plan" aria-labelledby="sr-vip-plan-<?php echo sr_theme_escape($plan['id'] ?? md5((string) ($plan['name'] ?? 'plan'))); ?>" data-price-source="<?php echo sr_theme_escape($priceSource); ?>">
                        <h3 id="sr-vip-plan-<?php echo sr_theme_escape($plan['id'] ?? md5((string) ($plan['name'] ?? 'plan'))); ?>"><?php echo sr_theme_escape($plan['name'] ?? 'VIP 套餐'); ?></h3>
                        <p class="sr-vip-plan__price"><?php echo sr_theme_escape($plan['price_label'] ?? '等待 EDD 价格'); ?></p>
                        <p class="sr-vip-plan__quota"><?php echo sr_theme_escape($plan['quota_label'] ?? '配额待服务层注入'); ?></p>

                        <div>
                            <h4><?php echo sr_theme_escape('包含范围'); ?></h4>
                            <?php echo sr_theme_vip_list($scope, 'sr-vip-plan__scope'); ?>
                        </div>

                        <div>
                            <h4><?php echo sr_theme_escape('排除项'); ?></h4>
                            <?php echo sr_theme_vip_list($exclusions, 'sr-vip-plan__exclusions'); ?>
                        </div>

                        <div class="sr-vip-plan__cta">
                            <?php echo sr_theme_vip_plan_cta($plan, $paymentEnabled); ?>
                        </div>
                    </section>
                <?php } ?>
            </section>
        <?php } ?>
    </article>
</main>

<?php get_template_part('partials/footer'); ?>
