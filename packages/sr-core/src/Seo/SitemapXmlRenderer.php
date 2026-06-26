<?php

declare(strict_types=1);

namespace StockResource\Core\Seo;

final readonly class SitemapXmlRenderer
{
    /** @param list<SitemapEntry> $entries */
    public function render(array $entries): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($entries as $entry) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.self::escape($entry->location).'</loc>';
            $lines[] = '    <lastmod>'.self::escape($entry->lastModified).'</lastmod>';
            $lines[] = '    <changefreq>'.self::escape($entry->changeFrequency).'</changefreq>';
            $lines[] = '    <priority>'.number_format($entry->priority, 1, '.', '').'</priority>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
