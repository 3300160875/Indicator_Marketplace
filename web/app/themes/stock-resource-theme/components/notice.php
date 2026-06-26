<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if (! function_exists('sr_theme_notice')) {
    /**
     * @param array<string, mixed> $args
     */
    function sr_theme_notice(array $args): string
    {
        $type = in_array($args['type'] ?? 'empty', ['empty', 'error'], true) ? (string) $args['type'] : 'empty';
        $title = trim((string) ($args['title'] ?? ''));
        $body = trim((string) ($args['body'] ?? ''));
        $attrs = [
            'class' => 'sr-notice sr-notice--' . $type,
            'role' => $type === 'error' ? 'alert' : 'status',
            'tabindex' => '-1',
        ];

        $html = '<section' . sr_theme_attrs($attrs) . '>';
        $html .= '<p class="sr-notice__title">' . sr_theme_escape($title) . '</p>';
        if ($body !== '') {
            $html .= '<p class="sr-notice__body">' . sr_theme_escape($body) . '</p>';
        }
        $html .= '</section>';

        return $html;
    }
}
