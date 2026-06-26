<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if (! function_exists('sr_theme_button')) {
    /**
     * @param array<string, mixed> $args
     */
    function sr_theme_button(array $args): string
    {
        $label = trim((string) ($args['label'] ?? ''));
        $href = trim((string) ($args['href'] ?? ''));
        $variant = in_array($args['variant'] ?? 'secondary', ['primary', 'secondary'], true)
            ? (string) ($args['variant'] ?? 'secondary')
            : 'secondary';
        $disabled = (bool) ($args['disabled'] ?? false);
        $class = 'sr-button sr-button--' . $variant;
        $attrs = [
            'class' => $class,
            'aria-disabled' => $disabled ? 'true' : null,
        ];

        if ($href !== '' && ! $disabled) {
            $attrs['href'] = $href;

            return '<a' . sr_theme_attrs($attrs) . '>' . sr_theme_escape($label) . '</a>';
        }

        return '<span' . sr_theme_attrs($attrs) . '>' . sr_theme_escape($label) . '</span>';
    }
}
