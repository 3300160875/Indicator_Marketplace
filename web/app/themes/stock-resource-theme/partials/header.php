<?php
declare(strict_types=1);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="sr-skip-link" href="#main"><?php esc_html_e('Skip to content', 'stock-resource-theme'); ?></a>
<header class="sr-site-header">
    <div class="sr-site-header__inner">
        <a class="sr-site-header__brand" href="<?php echo esc_url(home_url('/')); ?>">
            <?php bloginfo('name'); ?>
        </a>
    </div>
</header>
