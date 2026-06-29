<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

if (! function_exists('sr_theme_payment_timeline')) {
    /**
     * @param array<int, array<string, mixed>> $events
     * @param array<string, mixed> $options
     */
    function sr_theme_payment_timeline(array $events, array $options = []): string
    {
        $title = trim((string) ($options['title'] ?? '付款审核时间线'));
        $emptyMessage = trim((string) ($options['empty_message'] ?? '暂无审核记录'));

        $safeEvents = array_values(array_filter(
            $events,
            static fn (mixed $event): bool => is_array($event),
        ));

        if ($safeEvents === []) {
            return '<section class="sr-payment-timeline sr-payment-timeline--empty">'
                . '<p class="sr-payment-timeline__empty">' . sr_theme_escape($emptyMessage) . '</p>'
                . '</section>';
        }

        usort($safeEvents, static function (array $left, array $right): int {
            $leftTime = timelineEventTimestamp($left);
            $rightTime = timelineEventTimestamp($right);

            if ($leftTime === $rightTime) {
                return 0;
            }

            return ($leftTime < $rightTime) ? -1 : 1;
        });

        $items = '';
        foreach ($safeEvents as $event) {
            $items .= timelineRow((array) $event);
        }

        return '<section class="sr-payment-timeline sr-payment-timeline--with-items">'
            . '<h2 class="sr-payment-timeline__title">' . sr_theme_escape($title) . '</h2>'
            . '<div class="sr-payment-timeline__list">' . $items . '</div>'
            . '</section>';
    }

    function timelineRow(array $event): string
    {
        $state = trim((string) ($event['state'] ?? ''));
        $stateLabel = decisionStateLabel($state);
        $submittedAt = trim((string) ($event['submitted_at'] ?? ''));
        $reviewedAt = trim((string) ($event['reviewed_at'] ?? ''));
        $timeLabel = $reviewedAt !== '' ? $reviewedAt : $submittedAt;
        $userMessage = trim((string) ($event['user_message'] ?? ''));
        $stateClass = 'sr-payment-timeline__state--' . sanitizeTimelineState($state);

        return '<article class="sr-payment-timeline__item">'
            . '<p class="sr-payment-timeline__header">'
            . '<span class="sr-payment-timeline__state ' . sr_theme_escape($stateClass) . '">' . sr_theme_escape($stateLabel) . '</span>'
            . '<time class="sr-payment-timeline__time">' . sr_theme_escape($timeLabel === '' ? '-' : $timeLabel) . '</time>'
            . '</p>'
            . timelineUserMessage($userMessage)
            . '</article>';
    }

    function timelineUserMessage(string $userMessage): string
    {
        if ($userMessage === '') {
            return '';
        }

        return '<p class="sr-payment-timeline__message">' . sr_theme_escape($userMessage) . '</p>';
    }

    function decisionStateLabel(string $state): string
    {
        return match ($state) {
            'submitted' => '已提交',
            'under_review' => '财务审核中',
            'needs_more_info' => '待补充材料',
            'approved' => '已通过',
            'rejected' => '已驳回',
            'cancelled' => '已取消',
            default => '未知状态',
        };
    }

    function sanitizeTimelineState(string $state): string
    {
        return match ($state) {
            'submitted' => 'submitted',
            'under_review' => 'under-review',
            'needs_more_info' => 'needs-more-info',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
            default => 'unknown',
        };
    }

    function timelineEventTimestamp(array $event): int
    {
        $timestampSource = trim((string) ($event['reviewed_at'] ?? ''));
        if ($timestampSource === '') {
            $timestampSource = trim((string) ($event['submitted_at'] ?? ''));
        }

        if ($timestampSource === '') {
            return 0;
        }

        return strtotime($timestampSource) ?: 0;
    }
}
