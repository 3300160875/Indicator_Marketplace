<?php
declare(strict_types=1);

namespace StockResource\Entitlements\ContentRestriction;

use InvalidArgumentException;
use StockResource\Contracts\Entitlement\AccessDecision;

final readonly class ContentRestrictionService
{
    private mixed $decisionResolver;

    public function __construct(callable $decisionResolver)
    {
        $this->decisionResolver = $decisionResolver;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $runtime
     */
    public function renderShortcode(array $attributes, string $innerContent, array $runtime): RestrictedContentResult
    {
        return $this->render($this->requestFromShortcode($attributes, $runtime), $innerContent);
    }

    /**
     * @param array<string, mixed> $block
     * @param array<string, mixed> $runtime
     */
    public function renderBlock(array $block, string $innerContent, array $runtime): RestrictedContentResult
    {
        $attributes = $block['attrs'] ?? [];
        if (! is_array($attributes)) {
            $attributes = [];
        }

        return $this->render($this->requestFromBlock($attributes, $runtime), $innerContent);
    }

    private function render(ContentRestrictionRequest $request, string $innerContent): RestrictedContentResult
    {
        if ($request->isEditorPreview()) {
            $decision = AccessDecision::deny('editor_preview');

            return new RestrictedContentResult(
                visible: false,
                html: $this->placeholder($request, $decision),
                decision: $decision,
                reasonCode: 'editor_preview',
                cacheVary: $request->cacheVary(),
            );
        }

        $decision = ($this->decisionResolver)($request);
        if (! $decision instanceof AccessDecision) {
            throw new InvalidArgumentException('decision resolver must return AccessDecision.');
        }

        if ($decision->allowed) {
            return new RestrictedContentResult(
                visible: true,
                html: $innerContent,
                decision: $decision,
                reasonCode: $decision->reasonCode,
                cacheVary: $request->cacheVary(),
            );
        }

        return new RestrictedContentResult(
            visible: false,
            html: $this->placeholder($request, $decision),
            decision: $decision,
            reasonCode: $decision->reasonCode,
            cacheVary: $request->cacheVary(),
        );
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $runtime
     */
    private function requestFromShortcode(array $attributes, array $runtime): ContentRestrictionRequest
    {
        return $this->requestFromArrays(
            attributes: [
                'resource_id' => $attributes['resource_id'] ?? $attributes['resourceId'] ?? null,
                'access_mode' => $attributes['access_mode'] ?? $attributes['accessMode'] ?? null,
                'resource_status' => $attributes['resource_status'] ?? $attributes['resourceStatus'] ?? null,
                'taxonomy_term_ids' => $attributes['taxonomy_term_ids'] ?? $attributes['taxonomyTermIds'] ?? [],
                'preview_label' => $attributes['preview_label'] ?? $attributes['previewLabel'] ?? null,
            ],
            runtime: $runtime,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $runtime
     */
    private function requestFromBlock(array $attributes, array $runtime): ContentRestrictionRequest
    {
        return $this->requestFromArrays(
            attributes: [
                'resource_id' => $attributes['resourceId'] ?? $attributes['resource_id'] ?? null,
                'access_mode' => $attributes['accessMode'] ?? $attributes['access_mode'] ?? null,
                'resource_status' => $attributes['resourceStatus'] ?? $attributes['resource_status'] ?? null,
                'taxonomy_term_ids' => $attributes['taxonomyTermIds'] ?? $attributes['taxonomy_term_ids'] ?? [],
                'preview_label' => $attributes['previewLabel'] ?? $attributes['preview_label'] ?? null,
            ],
            runtime: $runtime,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $runtime
     */
    private function requestFromArrays(array $attributes, array $runtime): ContentRestrictionRequest
    {
        $resourceId = $this->positiveInt($attributes['resource_id'] ?? null, 'resource_id');
        $userId = $runtime['user_id'] ?? null;
        if ($userId !== null) {
            $userId = $this->positiveInt($userId, 'user_id');
        }

        return new ContentRestrictionRequest(
            resourceId: $resourceId,
            userId: $userId,
            accessMode: $this->stringValue($attributes['access_mode'] ?? 'purchase_or_vip', 'access_mode'),
            resourceStatus: $this->stringValue($attributes['resource_status'] ?? 'published', 'resource_status'),
            taxonomyTermIds: $this->positiveIntList($attributes['taxonomy_term_ids'] ?? []),
            surface: $this->stringValue($runtime['surface'] ?? 'frontend', 'surface'),
            atUtc: ContentRestrictionRequest::atom((string) ($runtime['at_utc'] ?? 'now')),
            previewLabel: $this->stringValue($attributes['preview_label'] ?? '受限内容占位', 'preview_label'),
        );
    }

    private function placeholder(ContentRestrictionRequest $request, AccessDecision $decision): string
    {
        $label = htmlspecialchars($request->previewLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $reason = htmlspecialchars($decision->reasonCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<div class="sr-restricted-placeholder" data-reason="'.$reason.'" data-resource-id="'.$request->resourceId.'">'.$label.'</div>';
    }

    private function positiveInt(mixed $value, string $field): int
    {
        if (! is_numeric($value) || (int) $value < 1) {
            throw new InvalidArgumentException($field.' must be positive.');
        }

        return (int) $value;
    }

    private function stringValue(mixed $value, string $field): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            throw new InvalidArgumentException($field.' is required.');
        }

        $string = trim((string) $value);
        if ($string === '') {
            throw new InvalidArgumentException($field.' is required.');
        }

        return $string;
    }

    /**
     * @return list<int>
     */
    private function positiveIntList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\s*,\s*/', trim($value), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (is_numeric($item) && (int) $item > 0) {
                $ids[(string) ((int) $item)] = (int) $item;
            }
        }
        ksort($ids);

        return array_values($ids);
    }
}
