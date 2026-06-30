<?php
declare(strict_types=1);

use StockResource\Contracts\Entitlement\AccessDecision;
use StockResource\Entitlements\ContentRestriction\ContentRestrictionRequest;
use StockResource\Entitlements\ContentRestriction\ContentRestrictionService;
use StockResource\Entitlements\ContentRestriction\RestrictedContentResult;

$root = dirname(__DIR__, 3);

foreach ([
    '/packages/sr-contracts/src/Entitlement/AccessDecision.php',
    '/packages/sr-entitlements/src/ContentRestriction/ContentRestrictionRequest.php',
    '/packages/sr-entitlements/src/ContentRestriction/RestrictedContentResult.php',
    '/packages/sr-entitlements/src/ContentRestriction/ContentRestrictionService.php',
] as $sourceFile) {
    require_once $root.$sourceFile;
}

function sr051_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr051_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr051_service(AccessDecision $decision): ContentRestrictionService
{
    return new ContentRestrictionService(static function (ContentRestrictionRequest $request) use ($decision): AccessDecision {
        return $decision;
    });
}

$hidden = '<p>付费用户专属公式：SECRET_ALPHA_123</p>';
$allowed = sr051_service(AccessDecision::allow(
    reasonCode: 'manual_grant',
    source: 'MANUAL',
    entitlementId: 501,
    grantType: 'manual',
    quota: ['available' => true],
    expiresAt: null,
    rulesVersion: 'rules-2026-06',
))->renderShortcode(
    attributes: [
        'resource_id' => 3001,
        'access_mode' => 'purchase_or_vip',
        'resource_status' => 'published',
        'taxonomy_term_ids' => '8,13',
    ],
    innerContent: $hidden,
    runtime: [
        'surface' => 'frontend',
        'user_id' => 1001,
        'at_utc' => '2026-06-30T01:00:00+00:00',
    ],
);
sr051_assert($allowed instanceof RestrictedContentResult, 'shortcode returns result object');
sr051_same(true, $allowed->visible, 'allowed frontend shortcode is visible');
sr051_same($hidden, $allowed->html, 'allowed frontend shortcode renders protected content');
sr051_same('manual_grant', $allowed->decision->reasonCode, 'allowed result carries access decision');
sr051_assert(in_array('user:1001', $allowed->cacheVary, true), 'allowed render varies by user');
sr051_assert(in_array('resource:3001', $allowed->cacheVary, true), 'allowed render varies by resource');

$deniedService = sr051_service(AccessDecision::deny('no_entitlement', source: 'VIP'));
$denied = $deniedService->renderShortcode(
    attributes: [
        'resource_id' => 3001,
        'access_mode' => 'purchase_or_vip',
        'resource_status' => 'published',
    ],
    innerContent: $hidden,
    runtime: [
        'surface' => 'frontend',
        'user_id' => 1002,
        'at_utc' => '2026-06-30T01:00:00+00:00',
    ],
);
sr051_same(false, $denied->visible, 'denied frontend shortcode is hidden');
sr051_assert(! str_contains($denied->html, 'SECRET_ALPHA_123'), 'denied frontend html does not leak hidden content');
sr051_assert(str_contains($denied->html, 'sr-restricted-placeholder'), 'denied frontend html has placeholder');
sr051_same('no_entitlement', $denied->decision->reasonCode, 'denied result carries reason');
sr051_assert(in_array('user:1002', $denied->cacheVary, true), 'denied render varies by user to avoid cache leakage');

$rest = $deniedService->renderBlock(
    block: [
        'attrs' => [
            'resourceId' => 3001,
            'accessMode' => 'purchase_or_vip',
            'resourceStatus' => 'published',
        ],
    ],
    innerContent: $hidden,
    runtime: [
        'surface' => 'rest',
        'user_id' => 1002,
        'at_utc' => '2026-06-30T01:00:00+00:00',
    ],
);
sr051_same(false, $rest->visible, 'REST block result is hidden for denied user');
sr051_assert(! str_contains($rest->toArray()['html'], 'SECRET_ALPHA_123'), 'REST payload html does not leak hidden content');
sr051_assert(! str_contains(json_encode($rest->toArray(), JSON_THROW_ON_ERROR), 'SECRET_ALPHA_123'), 'REST payload does not leak hidden content anywhere');

$preview = $deniedService->renderBlock(
    block: [
        'attrs' => [
            'resourceId' => 3001,
            'accessMode' => 'vip',
            'resourceStatus' => 'published',
            'previewLabel' => 'VIP 内容占位',
        ],
    ],
    innerContent: $hidden,
    runtime: [
        'surface' => 'editor',
        'user_id' => null,
        'at_utc' => '2026-06-30T01:00:00+00:00',
    ],
);
sr051_same(false, $preview->visible, 'editor preview never renders hidden content');
sr051_assert(str_contains($preview->html, 'VIP 内容占位'), 'editor preview has explicit placeholder label');
sr051_assert(! str_contains($preview->html, 'SECRET_ALPHA_123'), 'editor preview does not leak hidden content');
sr051_same('editor_preview', $preview->reasonCode, 'editor preview reason is stable');

try {
    $deniedService->renderShortcode(
        attributes: ['resource_id' => 0],
        innerContent: $hidden,
        runtime: ['surface' => 'frontend', 'user_id' => 1001],
    );
    throw new RuntimeException('invalid resource id should fail');
} catch (InvalidArgumentException $exception) {
    sr051_same('resource_id must be positive.', $exception->getMessage(), 'resource id validation fails closed');
}

echo "SR-051 content restriction checks passed.\n";
