<?php
declare(strict_types=1);

namespace StockResource\Entitlements\ContentRestriction;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use StockResource\Contracts\Entitlement\AccessDecisionContext;

final readonly class ContentRestrictionRequest
{
    /**
     * @param list<int> $taxonomyTermIds
     */
    public function __construct(
        public int $resourceId,
        public ?int $userId,
        public string $accessMode,
        public string $resourceStatus,
        public array $taxonomyTermIds,
        public string $surface,
        public string $atUtc,
        public string $previewLabel,
    ) {
        if ($this->resourceId < 1) {
            throw new InvalidArgumentException('resource_id must be positive.');
        }

        if ($this->userId !== null && $this->userId < 1) {
            throw new InvalidArgumentException('user_id must be positive when provided.');
        }

        if (! in_array($this->surface, ['frontend', 'rest', 'editor'], true)) {
            throw new InvalidArgumentException('surface is invalid.');
        }

        if (trim($this->previewLabel) === '') {
            throw new InvalidArgumentException('preview_label is required.');
        }
    }

    public function decisionContext(): AccessDecisionContext
    {
        return new AccessDecisionContext(
            resourceId: $this->resourceId,
            userId: $this->userId,
            accessMode: $this->accessMode,
            resourceStatus: $this->resourceStatus,
            taxonomyTermIds: $this->taxonomyTermIds,
            atUtc: $this->atUtc,
        );
    }

    public function isEditorPreview(): bool
    {
        return $this->surface === 'editor';
    }

    /**
     * @return list<string>
     */
    public function cacheVary(): array
    {
        $vary = [
            'resource:'.$this->resourceId,
            'surface:'.$this->surface,
            'access_mode:'.$this->accessMode,
        ];

        $vary[] = $this->userId === null ? 'user:anonymous' : 'user:'.$this->userId;

        sort($vary);

        return $vary;
    }

    public static function atom(string $datetime): string
    {
        $date = new DateTimeImmutable($datetime);

        return $date->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
    }
}
