<?php
declare(strict_types=1);

add_action('after_setup_theme', static function (): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('responsive-embeds');
});

add_action('wp_enqueue_scripts', static function (): void {
    $theme = wp_get_theme();
    $version = $theme->get('Version') ?: '0.1.0';

    wp_enqueue_style(
        'stock-resource-theme',
        get_template_directory_uri() . '/assets/css/theme.css',
        [],
        $version,
    );

    wp_enqueue_script(
        'stock-resource-theme',
        get_template_directory_uri() . '/assets/js/theme.js',
        [],
        $version,
        true,
    );
});
