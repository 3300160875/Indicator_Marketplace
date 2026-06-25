<?php
declare(strict_types=1);

get_template_part('partials/header');
?>

<main class="sr-site-main" id="main">
    <section class="sr-content sr-content--empty">
        <h1><?php esc_html_e('Page not found', 'stock-resource-theme'); ?></h1>
        <p><?php esc_html_e('The requested page could not be found.', 'stock-resource-theme'); ?></p>
    </section>
</main>

<?php get_template_part('partials/footer'); ?>
