<?php
declare(strict_types=1);

namespace StockResource\Core\Content\Taxonomy;

use InvalidArgumentException;

final readonly class ControlledVocabulary
{
    /** @param array<string, list<array{slug: string, name: string}>> $terms */
    private function __construct(private array $terms)
    {
    }

    public static function defaults(): self
    {
        return new self([
            'sr_platform' => [
                ['slug' => 'tongdaxin', 'name' => '通达信'],
                ['slug' => 'tonghuashun', 'name' => '同花顺'],
                ['slug' => 'dongfangcaifu', 'name' => '东方财富'],
            ],
            'sr_indicator_type' => [
                ['slug' => 'main-chart', 'name' => '主图'],
                ['slug' => 'sub-chart', 'name' => '副图'],
                ['slug' => 'stock-screening', 'name' => '选股'],
                ['slug' => 'alert', 'name' => '预警'],
                ['slug' => 'ranking', 'name' => '排序'],
            ],
            'sr_strategy_tag' => [
                ['slug' => 'trend-following', 'name' => '趋势跟随'],
                ['slug' => 'breakout', 'name' => '突破'],
                ['slug' => 'volume-price', 'name' => '量价'],
            ],
            'sr_content_type' => [
                ['slug' => 'indicator', 'name' => '指标'],
                ['slug' => 'source-code', 'name' => '源码'],
                ['slug' => 'tutorial', 'name' => '教程'],
                ['slug' => 'tool', 'name' => '工具'],
            ],
        ]);
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    public function termsFor(string $taxonomy): array
    {
        return $this->terms[$taxonomy] ?? [];
    }

    public static function normalizeSlug(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        if ($normalized === '') {
            throw new InvalidArgumentException('Taxonomy slug must contain ASCII letters or numbers.');
        }

        return $normalized;
    }
}
