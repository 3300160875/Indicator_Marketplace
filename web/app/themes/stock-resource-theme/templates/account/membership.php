<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/components/helpers.php';
require_once dirname(__DIR__, 2).'/components/notice.php';

if (! function_exists('sr_theme_account_membership_status')) {
    function sr_theme_account_membership_status(mixed $status): string
    {
        return in_array($status, ['ready', 'loading', 'empty', 'error', 'restricted'], true)
            ? (string) $status
            : 'empty';
    }
}

if (! function_exists('sr_theme_account_membership_text')) {
    function sr_theme_account_membership_text(mixed $value, string $fallback = '未提供'): string
    {
        $text = trim((string) $value);

        return $text === '' ? $fallback : $text;
    }
}

if (! function_exists('sr_theme_account_membership_render')) {
    /**
     * @param array<string, mixed> $membership
     */
    function sr_theme_account_membership_render(array $membership): string
    {
        $state = sr_theme_account_membership_status($membership['state'] ?? 'empty');
        $items = is_array($membership['entitlements'] ?? null)
            ? array_values(array_filter($membership['entitlements'], 'is_array'))
            : [];

        $html = '<section class="sr-account-section sr-account-section--membership" aria-labelledby="sr-account-membership" data-state="'.sr_theme_escape($state).'">';
        $html .= '<h2 id="sr-account-membership">'.sr_theme_escape('会员权益').'</h2>';

        if ($state !== 'ready' || $items === []) {
            $title = match ($state) {
                'loading' => '会员权益加载中',
                'error' => '会员权益暂不可用',
                'restricted' => '暂无权限查看会员权益',
                default => '暂无会员权益',
            };
            $html .= sr_theme_notice([
                'type' => $state === 'error' ? 'error' : 'empty',
                'title' => $title,
            ]);
            $html .= '</section>';

            return $html;
        }

        $html .= '<ul class="sr-account-list sr-account-list--membership">';
        foreach ($items as $item) {
            $plan = is_array($item['plan'] ?? null) ? $item['plan'] : [];
            $scope = is_array($item['scope'] ?? null) ? $item['scope'] : [];
            $quota = is_array($item['quota'] ?? null) ? $item['quota'] : [];
            $html .= '<li class="sr-account-list__item" data-entitlement-id="'.sr_theme_escape($item['id'] ?? '').'">';
            $html .= '<p class="sr-account-list__title">'.sr_theme_escape(sr_theme_account_membership_text($plan['name'] ?? null, '会员权益')).'</p>';
            $html .= '<dl class="sr-resource-meta">';
            $html .= '<div class="sr-resource-meta__item"><dt class="sr-resource-meta__label">'.sr_theme_escape('状态').'</dt><dd class="sr-resource-meta__value">'.sr_theme_escape($item['status'] ?? 'unknown').'</dd></div>';
            $html .= '<div class="sr-resource-meta__item"><dt class="sr-resource-meta__label">'.sr_theme_escape('到期').'</dt><dd class="sr-resource-meta__value">'.sr_theme_escape(sr_theme_account_membership_text($item['expires_at'] ?? null, '长期有效')).'</dd></div>';
            $html .= '<div class="sr-resource-meta__item"><dt class="sr-resource-meta__label">'.sr_theme_escape('范围').'</dt><dd class="sr-resource-meta__value">'.sr_theme_escape(sr_theme_account_membership_text($scope['type'] ?? null, '未提供')).'</dd></div>';
            $html .= '<div class="sr-resource-meta__item"><dt class="sr-resource-meta__label">'.sr_theme_escape('剩余次数').'</dt><dd class="sr-resource-meta__value">'.sr_theme_escape($quota['remaining'] ?? '不限').'</dd></div>';
            $html .= '<div class="sr-resource-meta__item"><dt class="sr-resource-meta__label">'.sr_theme_escape('重置时间').'</dt><dd class="sr-resource-meta__value">'.sr_theme_escape(sr_theme_account_membership_text($quota['reset_at'] ?? null, '不重置')).'</dd></div>';
            $html .= '</dl></li>';
        }
        $html .= '</ul></section>';

        return $html;
    }
}
