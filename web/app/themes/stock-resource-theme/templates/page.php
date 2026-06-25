<?php
declare(strict_types=1);

get_template_part('partials/header');
?>

<main class="sr-site-main" id="main">
    <?php while (have_posts()) : the_post(); ?>
        <article <?php post_class('sr-content sr-content--page'); ?>>
            <h1 class="sr-content__title"><?php the_title(); ?></h1>
            <div class="sr-content__body">
                <?php the_content(); ?>
            </div>
        </article>
    <?php endwhile; ?>
</main>

<?php get_template_part('partials/footer'); ?>
