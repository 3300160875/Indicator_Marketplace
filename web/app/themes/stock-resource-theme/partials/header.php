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
        <nav class="sr-primary-nav" aria-label="主导航">
            <a class="sr-primary-nav__link" href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html__('首页', 'stock-resource-theme'); ?></a>
            <a class="sr-primary-nav__link" href="<?php echo esc_url(function_exists('sr_theme_resource_archive_url') ? sr_theme_resource_archive_url() : home_url('/resources/')); ?>"><?php echo esc_html__('资源', 'stock-resource-theme'); ?></a>
            <a class="sr-primary-nav__link" href="<?php echo esc_url(home_url('/resource-topics/')); ?>"><?php echo esc_html__('专题', 'stock-resource-theme'); ?></a>
            <a class="sr-primary-nav__link" href="<?php echo esc_url(home_url('/account/')); ?>"><?php echo esc_html__('账户', 'stock-resource-theme'); ?></a>
        </nav>
    </div>
</header>
