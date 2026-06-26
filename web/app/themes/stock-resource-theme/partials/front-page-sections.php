<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/components/button.php';
require_once dirname(__DIR__) . '/components/notice.php';
require_once dirname(__DIR__) . '/components/resource-card.php';

if (! function_exists('sr_theme_front_action')) {
    /**
     * @param array<string, mixed> $action
     */
    function sr_theme_front_action(array $action, string $variant = 'primary'): string
    {
        return sr_theme_button([
            'label' => (string) ($action['label'] ?? ''),
            'href' => (string) ($action['href'] ?? ''),
            'variant' => $variant,
        ]);
    }
}

if (! function_exists('sr_theme_render_front_hero')) {
    /**
     * @param array<string, mixed> $hero
     */
    function sr_theme_render_front_hero(array $hero): void
    {
        ?>
        <section class="sr-front-hero" aria-labelledby="sr-front-title">
            <p class="sr-front-hero__eyebrow"><?php echo sr_theme_escape($hero['eyebrow'] ?? ''); ?></p>
            <h1 id="sr-front-title"><?php echo sr_theme_escape($hero['title'] ?? ''); ?></h1>
            <p class="sr-front-hero__summary"><?php echo sr_theme_escape($hero['summary'] ?? ''); ?></p>
            <div class="sr-front-hero__actions">
                <?php
                if (is_array($hero['primary_action'] ?? null)) {
                    echo sr_theme_front_action($hero['primary_action'], 'primary');
                }
                if (is_array($hero['secondary_action'] ?? null)) {
                    echo sr_theme_front_action($hero['secondary_action'], 'secondary');
                }
                ?>
            </div>
        </section>
        <?php
    }
}

if (! function_exists('sr_theme_render_front_topics')) {
    /**
     * @param list<array<string, mixed>> $topics
     */
    function sr_theme_render_front_topics(array $topics): void
    {
        ?>
        <section class="sr-front-topics" aria-labelledby="sr-front-topics-title">
            <div class="sr-section-heading">
                <h2 id="sr-front-topics-title"><?php echo sr_theme_escape('专题区'); ?></h2>
                <p><?php echo sr_theme_escape('围绕平台、指标类型和策略标签快速进入资源集合。'); ?></p>
            </div>
            <div class="sr-front-topics__grid">
                <?php foreach ($topics as $topic) : ?>
                    <article class="sr-front-topic">
                        <h3><a href="<?php echo sr_theme_escape((string) ($topic['href'] ?? '#')); ?>"><?php echo sr_theme_escape($topic['title'] ?? ''); ?></a></h3>
                        <p><?php echo sr_theme_escape($topic['summary'] ?? ''); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }
}

if (! function_exists('sr_theme_render_front_featured')) {
    /**
     * @param list<array<string, mixed>> $resources
     */
    function sr_theme_render_front_featured(array $resources): void
    {
        ?>
        <section class="sr-front-featured" aria-labelledby="sr-front-featured-title">
            <div class="sr-section-heading">
                <h2 id="sr-front-featured-title"><?php echo sr_theme_escape('精选资源'); ?></h2>
                <p><?php echo sr_theme_escape('来自公开资源 DTO 的首页展示数据。'); ?></p>
            </div>
            <div class="sr-front-featured__grid">
                <?php foreach ($resources as $resource) : ?>
                    <?php echo sr_theme_resource_card($resource); ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }
}

if (! function_exists('sr_theme_render_front_empty')) {
    /**
     * @param array<string, mixed> $empty
     */
    function sr_theme_render_front_empty(array $empty): void
    {
        ?>
        <section class="sr-front-empty" aria-labelledby="sr-front-empty-title">
            <?php
            echo sr_theme_notice([
                'type' => 'empty',
                'title' => (string) ($empty['title'] ?? ''),
                'body' => (string) ($empty['body'] ?? ''),
            ]);
            if (is_array($empty['action'] ?? null)) {
                echo sr_theme_front_action($empty['action'], 'secondary');
            }
            ?>
        </section>
        <?php
    }
}
