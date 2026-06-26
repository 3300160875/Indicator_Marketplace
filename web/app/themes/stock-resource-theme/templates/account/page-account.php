<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/components/helpers.php';
require_once dirname(__DIR__, 2).'/components/button.php';
require_once dirname(__DIR__, 2).'/components/notice.php';

if (! function_exists('sr_theme_account_page_model')) {
    /**
     * @return array<string, mixed>
     */
    function sr_theme_account_page_model(): array
    {
        $model = [
            'status' => 'ready',
            'is_logged_in' => false,
            'owner_verified' => false,
            'login_url' => wp_login_url(home_url('/account/')),
            'user' => [
                'display_name' => '',
                'email_label' => '',
            ],
            'sections' => [
                'orders' => [
                    'state' => 'empty',
                    'title' => '订单中心',
                    'items' => [],
                ],
                'downloads' => [
                    'state' => 'empty',
                    'title' => '下载中心',
                    'items' => [],
                ],
            ],
        ];

        /** @var array<string, mixed> $filtered */
        $filtered = apply_filters('sr_theme_account_page_model', $model);

        return is_array($filtered) ? $filtered : $model;
    }
}

if (! function_exists('sr_theme_account_status')) {
    function sr_theme_account_status(mixed $status): string
    {
        return in_array($status, ['ready', 'loading', 'empty', 'error', 'restricted'], true)
            ? (string) $status
            : 'ready';
    }
}

if (! function_exists('sr_theme_account_section_state')) {
    function sr_theme_account_section_state(mixed $state): string
    {
        return in_array($state, ['ready', 'loading', 'empty', 'error', 'restricted'], true)
            ? (string) $state
            : 'empty';
    }
}

if (! function_exists('sr_theme_account_section_notice')) {
    function sr_theme_account_section_notice(string $section, string $state): string
    {
        $title = match ([$section, $state]) {
            ['orders', 'loading'] => '订单加载中',
            ['orders', 'error'] => '订单暂不可用',
            ['orders', 'restricted'] => '暂无权限查看订单',
            ['orders', 'empty'] => '暂无订单记录',
            ['downloads', 'loading'] => '下载记录加载中',
            ['downloads', 'error'] => '下载中心暂不可用',
            ['downloads', 'restricted'] => '暂无权限查看下载',
            ['downloads', 'empty'] => '暂无下载记录',
            default => '暂无可展示内容',
        };

        return sr_theme_notice([
            'type' => $state === 'error' ? 'error' : 'empty',
            'title' => $title,
        ]);
    }
}

if (! function_exists('sr_theme_account_section')) {
    /**
     * @param  array<string, mixed>  $section
     */
    function sr_theme_account_section(string $key, array $section): string
    {
        $state = sr_theme_account_section_state($section['state'] ?? 'empty');
        $title = trim((string) ($section['title'] ?? ($key === 'orders' ? '订单中心' : '下载中心')));
        $items = is_array($section['items'] ?? null) ? array_values(array_filter($section['items'], 'is_array')) : [];
        $id = 'sr-account-'.$key;
        $html = '<section class="sr-account-section sr-account-section--'.sr_theme_escape($key).'" aria-labelledby="'.sr_theme_escape($id).'" data-state="'.sr_theme_escape($state).'">';
        $html .= '<h2 id="'.sr_theme_escape($id).'">'.sr_theme_escape($title).'</h2>';

        if ($state !== 'ready' || $items === []) {
            $html .= sr_theme_account_section_notice($key, $state === 'ready' ? 'empty' : $state);
            $html .= '</section>';

            return $html;
        }

        $html .= '<ul class="sr-account-list">';
        foreach ($items as $item) {
            $itemId = trim((string) ($item['id'] ?? ''));
            $html .= '<li class="sr-account-list__item"'.($itemId === '' ? '' : ' data-item-id="'.sr_theme_escape($itemId).'"').'>';
            $html .= '<p class="sr-account-list__title">'.sr_theme_escape($item['title'] ?? '').'</p>';
            $meta = trim((string) ($item['meta'] ?? ''));
            if ($meta !== '') {
                $html .= '<p class="sr-account-list__meta">'.sr_theme_escape($meta).'</p>';
            }
            $html .= '</li>';
        }
        $html .= '</ul></section>';

        return $html;
    }
}

$accountModel = sr_theme_account_page_model();
$status = sr_theme_account_status($accountModel['status'] ?? 'ready');
$isLoggedIn = (bool) ($accountModel['is_logged_in'] ?? false);
$ownerVerified = (bool) ($accountModel['owner_verified'] ?? false);
$user = is_array($accountModel['user'] ?? null) ? $accountModel['user'] : [];
$sections = is_array($accountModel['sections'] ?? null) ? $accountModel['sections'] : [];
$loginUrl = trim((string) ($accountModel['login_url'] ?? wp_login_url(home_url('/account/'))));

get_template_part('partials/header');
?>

<main class="sr-site-main" id="main">
    <article class="sr-account-shell" aria-labelledby="sr-account-title" data-authenticated="<?php echo $isLoggedIn ? 'true' : 'false'; ?>" data-owner-verified="<?php echo $ownerVerified ? 'true' : 'false'; ?>">
        <header class="sr-account-header">
            <p class="sr-front-hero__eyebrow"><?php echo sr_theme_escape('账户中心'); ?></p>
            <h1 id="sr-account-title"><?php echo sr_theme_escape('用户中心'); ?></h1>
            <p><?php echo sr_theme_escape('集中查看登录状态、订单记录和已授权下载资源。'); ?></p>
        </header>

        <?php if ($status === 'loading') { ?>
            <?php echo sr_theme_notice(['type' => 'empty', 'title' => '用户中心加载中', 'body' => '正在等待账户服务返回用户中心数据。']); ?>
        <?php } elseif ($status === 'error') { ?>
            <?php echo sr_theme_notice(['type' => 'error', 'title' => '用户中心暂不可用', 'body' => '请稍后重试，异常状态不会展示订单或下载记录。']); ?>
        <?php } elseif (! $isLoggedIn) { ?>
            <section class="sr-account-gate" aria-labelledby="sr-account-login-title">
                <h2 id="sr-account-login-title"><?php echo sr_theme_escape('登录后查看用户中心'); ?></h2>
                <p><?php echo sr_theme_escape('订单和下载记录仅对当前登录用户可见。'); ?></p>
                <?php echo sr_theme_button(['label' => '登录', 'href' => $loginUrl, 'variant' => 'primary']); ?>
            </section>
        <?php } elseif (! $ownerVerified || $status === 'restricted') { ?>
            <?php echo sr_theme_notice(['type' => 'error', 'title' => '当前账号无权查看此用户中心', 'body' => '请确认登录账号与订单所有者一致。']); ?>
        <?php } else { ?>
            <section class="sr-account-summary" aria-labelledby="sr-account-summary-title">
                <h2 id="sr-account-summary-title"><?php echo sr_theme_escape('账户概览'); ?></h2>
                <dl class="sr-resource-meta">
                    <div class="sr-resource-meta__item">
                        <dt class="sr-resource-meta__label"><?php echo sr_theme_escape('用户名'); ?></dt>
                        <dd class="sr-resource-meta__value"><?php echo sr_theme_escape($user['display_name'] ?? '当前用户'); ?></dd>
                    </div>
                    <div class="sr-resource-meta__item">
                        <dt class="sr-resource-meta__label"><?php echo sr_theme_escape('邮箱'); ?></dt>
                        <dd class="sr-resource-meta__value"><?php echo sr_theme_escape($user['email_label'] ?? '未提供'); ?></dd>
                    </div>
                </dl>
            </section>

            <nav class="sr-account-nav" aria-label="<?php echo sr_theme_escape('用户中心导航'); ?>">
                <a href="#sr-account-orders"><?php echo sr_theme_escape('订单中心'); ?></a>
                <a href="#sr-account-downloads"><?php echo sr_theme_escape('下载中心'); ?></a>
            </nav>

            <?php echo sr_theme_account_section('orders', is_array($sections['orders'] ?? null) ? $sections['orders'] : []); ?>
            <?php echo sr_theme_account_section('downloads', is_array($sections['downloads'] ?? null) ? $sections['downloads'] : []); ?>
        <?php } ?>
    </article>
</main>

<?php get_template_part('partials/footer'); ?>
