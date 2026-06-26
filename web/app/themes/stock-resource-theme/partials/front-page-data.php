<?php
declare(strict_types=1);

if (! function_exists('sr_theme_resource_archive_url')) {
    function sr_theme_resource_archive_url(): string
    {
        $archive = function_exists('get_post_type_archive_link') ? get_post_type_archive_link('download') : '';

        return is_string($archive) && $archive !== '' ? $archive : home_url('/resources/');
    }
}

if (! function_exists('sr_theme_front_page_model')) {
    /**
     * @return array<string, mixed>
     */
    function sr_theme_front_page_model(): array
    {
        $archiveUrl = sr_theme_resource_archive_url();
        $model = [
            'hero' => [
                'eyebrow' => '股票指标资源库',
                'title' => '更快找到可验证、可下载的指标资源',
                'summary' => '按平台、指标类型和使用场景整理资源，优先展示已通过公开 DTO 的内容。',
                'primary_action' => ['label' => '浏览资源', 'href' => $archiveUrl],
                'secondary_action' => ['label' => '查看分类', 'href' => home_url('/resource-topics/')],
            ],
            'topics' => [
                ['title' => '平台适配', 'summary' => '按通达信、同花顺、东方财富等平台组织。', 'href' => $archiveUrl . '?platform=tongdaxin'],
                ['title' => '指标类型', 'summary' => '主图、副图、选股、预警等类型入口。', 'href' => $archiveUrl . '?indicator_type=sub-chart'],
                ['title' => '策略专题', 'summary' => '趋势、突破、量价等策略标签聚合。', 'href' => $archiveUrl . '?strategy_tag=trend-following'],
            ],
            'featured' => [
                [
                    'title' => '通达信趋势观察指标',
                    'excerpt' => '适合趋势识别和盘后复盘的公开示例资源。',
                    'href' => $archiveUrl . 'tdx-trend/',
                    'access_mode' => 'purchase_or_vip',
                    'meta' => ['platform' => '通达信', 'indicator_type' => '副图', 'software_versions' => ['7.60']],
                ],
                [
                    'title' => '量价突破选股模板',
                    'excerpt' => '围绕量价关系构建的选股模板入口。',
                    'href' => $archiveUrl . 'volume-breakout/',
                    'access_mode' => 'purchase',
                    'meta' => ['platform' => '通达信', 'indicator_type' => '选股', 'software_versions' => ['7.60']],
                ],
            ],
            'empty' => [
                'title' => '暂未加载到更多公开资源',
                'body' => '可以先进入资源列表按平台或类型筛选。',
                'action' => ['label' => '打开资源列表', 'href' => $archiveUrl],
            ],
        ];

        /** @var array<string, mixed> $filtered */
        $filtered = apply_filters('sr_theme_front_page_model', $model);

        return is_array($filtered) ? $filtered : $model;
    }
}
