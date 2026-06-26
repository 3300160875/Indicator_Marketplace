<?php
declare(strict_types=1);

if (! function_exists('sr_theme_escape')) {
    function sr_theme_escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('sr_theme_attrs')) {
    /**
     * @param array<string, mixed> $attrs
     */
    function sr_theme_attrs(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            $parts[] = sr_theme_escape($name) . '="' . sr_theme_escape($value === true ? $name : $value) . '"';
        }

        return $parts === [] ? '' : ' ' . implode(' ', $parts);
    }
}

if (! function_exists('sr_theme_access_label')) {
    function sr_theme_access_label(string $accessMode): string
    {
        return match ($accessMode) {
            'free' => '免费',
            'purchase' => '单独购买',
            'vip' => 'VIP 包含',
            'purchase_or_vip' => '购买或 VIP',
            default => '暂不可用',
        };
    }
}

if (! function_exists('sr_theme_unknown')) {
    function sr_theme_unknown(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode(' / ', array_filter(array_map('strval', $value)));
        }

        $value = trim((string) ($value ?? ''));

        return $value === '' ? '未核实' : $value;
    }
}
