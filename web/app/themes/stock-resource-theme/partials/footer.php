<?php
declare(strict_types=1);
?>
<footer class="sr-site-footer">
    <div class="sr-site-footer__inner">
        <p>&copy; <?php echo esc_html((string) gmdate('Y')); ?> <?php bloginfo('name'); ?></p>
        <nav class="sr-footer-nav" aria-label="页脚导航">
            <a class="sr-footer-nav__link" href="<?php echo esc_url(home_url('/resources/')); ?>"><?php echo esc_html__('资源列表', 'stock-resource-theme'); ?></a>
            <a class="sr-footer-nav__link" href="<?php echo esc_url(home_url('/resource-topics/')); ?>"><?php echo esc_html__('专题区', 'stock-resource-theme'); ?></a>
            <a class="sr-footer-nav__link" href="<?php echo esc_url(home_url('/support/')); ?>"><?php echo esc_html__('支持', 'stock-resource-theme'); ?></a>
        </nav>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
