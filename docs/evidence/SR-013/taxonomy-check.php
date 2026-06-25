<?php
declare(strict_types=1);

use StockResource\Core\Content\Taxonomy\ControlledVocabulary;
use StockResource\Core\Content\Taxonomy\TaxonomyCatalog;
use StockResource\Core\Content\Taxonomy\TaxonomyDefinition;
use StockResource\Core\Content\Taxonomy\TaxonomyRestSchema;
use StockResource\Core\Content\Taxonomy\TermDeletionGuard;

$root = dirname(__DIR__, 3);
foreach ([
    '/packages/sr-core/src/Content/Taxonomy/TaxonomyDefinition.php',
    '/packages/sr-core/src/Content/Taxonomy/ControlledVocabulary.php',
    '/packages/sr-core/src/Content/Taxonomy/TaxonomyCatalog.php',
    '/packages/sr-core/src/Content/Taxonomy/TaxonomyRestSchema.php',
    '/packages/sr-core/src/Content/Taxonomy/TermDeletionGuard.php',
] as $file) {
    require_once $root . $file;
}

function sr_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

$catalog = TaxonomyCatalog::defaults();
$definitions = $catalog->definitions();
sr_same([
    'download_category',
    'sr_platform',
    'sr_indicator_type',
    'sr_strategy_tag',
    'sr_content_type',
], array_keys($definitions), 'catalog exposes the five required taxonomies');

$platform = $definitions['sr_platform'];
sr_assert($platform instanceof TaxonomyDefinition, 'platform taxonomy is a definition');
sr_same('platform', $platform->restKey(), 'platform REST key is stable');
sr_same('/platform/{slug}/', $platform->rewritePattern(), 'platform rewrite pattern is stable');
sr_assert($platform->showUi(), 'platform is manageable in wp-admin');
sr_assert($platform->showInRest(), 'platform is exposed to REST schema');

$vocabulary = ControlledVocabulary::defaults();
sr_same(['tongdaxin', 'tonghuashun', 'dongfangcaifu'], array_column($vocabulary->termsFor('sr_platform'), 'slug'), 'platform seed slugs are stable');
sr_same(['main-chart', 'sub-chart', 'stock-screening', 'alert', 'ranking'], array_column($vocabulary->termsFor('sr_indicator_type'), 'slug'), 'indicator type slugs are stable');
sr_same(['indicator', 'source-code', 'tutorial', 'tool'], array_column($vocabulary->termsFor('sr_content_type'), 'slug'), 'content type slugs are stable');

$schema = new TaxonomyRestSchema();
$term = $schema->term('sr_platform', 'tongdaxin', '通达信', 12);
sr_same([
    'taxonomy' => 'sr_platform',
    'slug' => 'tongdaxin',
    'name' => '通达信',
    'count' => 12,
], $term, 'REST taxonomy term schema exposes stable public fields');

try {
    ControlledVocabulary::normalizeSlug('通达信');
    throw new RuntimeException('non-ascii slug was accepted');
} catch (InvalidArgumentException) {
}

sr_same('tong-da-xin', ControlledVocabulary::normalizeSlug('Tong Da Xin'), 'slug normalization is stable ascii');

$guard = new TermDeletionGuard(references: ['sr_platform:tongdaxin' => 3]);
sr_assert(! $guard->canDelete('sr_platform', 'tongdaxin'), 'referenced term deletion is blocked');
sr_assert($guard->canDelete('sr_platform', 'wenhua'), 'unreferenced term deletion is allowed');
sr_same('referenced_term_requires_migration', $guard->deletionError('sr_platform', 'tongdaxin'), 'referenced deletion returns stable error');

echo "SR-013 taxonomy check: ok\n";
