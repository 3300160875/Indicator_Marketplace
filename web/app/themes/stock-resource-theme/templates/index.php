<?php
declare(strict_types=1);

get_template_part('partials/header');
?>

<main class="sr-site-main" id="main">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class('sr-content'); ?>>
                <h1 class="sr-content__title"><?php the_title(); ?></h1>
                <div class="sr-content__body">
                    <?php the_content(); ?>
                </div>
            </article>
        <?php endwhile; ?>
    <?php else : ?>
        <section class="sr-content sr-content--empty">
            <h1><?php esc_html_e('Content coming soon', 'stock-resource-theme'); ?></h1>
        </section>
    <?php endif; ?>
</main>

<?php get_template_part('partials/footer'); ?>
