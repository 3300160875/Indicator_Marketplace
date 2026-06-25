<?php
declare(strict_types=1);

get_template_part('partials/header');
?>

<main class="sr-site-main" id="main">
    <section class="sr-front">
        <h1><?php bloginfo('name'); ?></h1>
        <p><?php bloginfo('description'); ?></p>
    </section>
</main>

<?php get_template_part('partials/footer'); ?>
