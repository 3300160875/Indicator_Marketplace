<?php
declare(strict_types=1);

get_template_part('partials/header');
require_once dirname(__DIR__) . '/partials/front-page-data.php';
require_once dirname(__DIR__) . '/partials/front-page-sections.php';

$frontPageModel = sr_theme_front_page_model();
?>

<main class="sr-site-main" id="main">
    <div class="sr-front">
        <?php
        sr_theme_render_front_hero(is_array($frontPageModel['hero'] ?? null) ? $frontPageModel['hero'] : []);
        sr_theme_render_front_topics(is_array($frontPageModel['topics'] ?? null) ? $frontPageModel['topics'] : []);
        sr_theme_render_front_featured(is_array($frontPageModel['featured'] ?? null) ? $frontPageModel['featured'] : []);
        sr_theme_render_front_empty(is_array($frontPageModel['empty'] ?? null) ? $frontPageModel['empty'] : []);
        ?>
    </div>
</main>

<?php get_template_part('partials/footer'); ?>
