<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/components/filter/archive-query.php';
require_once dirname(__DIR__) . '/components/filter/archive-controls.php';
require_once dirname(__DIR__) . '/components/resource-card.php';
require_once dirname(__DIR__) . '/components/notice.php';

$archiveQuery = sr_theme_archive_query_from_request($_GET);
$archiveModel = $archiveQuery['valid'] ? sr_theme_archive_download_model($archiveQuery) : [
    'resources' => [],
    'pagination' => ['page' => 1, 'per_page' => 12, 'total' => 0, 'total_pages' => 0],
    'vocabulary' => [
        'platform' => [['slug' => 'tongdaxin', 'name' => '通达信']],
        'indicator_type' => [['slug' => 'sub-chart', 'name' => '副图']],
        'content_type' => [['slug' => 'indicator', 'name' => '指标']],
    ],
];

get_template_part('partials/header');
?>
<?php if (($archiveQuery['robots'] ?? 'index,follow') !== 'index,follow') : ?>
    <meta name="robots" content="<?php echo sr_theme_escape($archiveQuery['robots']); ?>">
<?php endif; ?>
<link rel="canonical" href="<?php echo sr_theme_escape($archiveQuery['canonical_url'] ?? sr_theme_archive_base_url()); ?>">

<main class="sr-site-main" id="main">
    <section class="sr-archive" aria-labelledby="sr-archive-title">
        <div class="sr-section-heading">
            <h1 id="sr-archive-title"><?php echo sr_theme_escape('资源列表'); ?></h1>
            <p><?php echo sr_theme_escape('筛选条件会进入 URL，便于分享和返回。'); ?></p>
        </div>

        <?php echo sr_theme_archive_filter_controls($archiveQuery, $archiveModel['vocabulary']); ?>

        <?php if (! $archiveQuery['valid']) : ?>
            <?php
            echo sr_theme_notice([
                'type' => 'error',
                'title' => '筛选条件无效',
                'body' => '已阻止该组合被索引，请重置后重新筛选。',
            ]);
            echo sr_theme_button(['label' => '重置筛选', 'href' => (string) $archiveQuery['reset_url'], 'variant' => 'secondary']);
            ?>
        <?php elseif ($archiveModel['resources'] === []) : ?>
            <div class="sr-archive-empty">
                <?php
                echo sr_theme_notice([
                    'type' => 'empty',
                    'title' => '没有匹配的资源',
                    'body' => '可以清空条件后重新查看全部资源。',
                ]);
                echo sr_theme_button(['label' => '查看全部资源', 'href' => (string) $archiveQuery['reset_url'], 'variant' => 'secondary']);
                ?>
            </div>
        <?php else : ?>
            <div class="sr-archive-results">
                <?php foreach ($archiveModel['resources'] as $resource) : ?>
                    <?php echo sr_theme_resource_card($resource); ?>
                <?php endforeach; ?>
            </div>
            <?php echo sr_theme_archive_pagination($archiveQuery, $archiveModel['pagination']); ?>
        <?php endif; ?>
    </section>
</main>

<?php get_template_part('partials/footer'); ?>
