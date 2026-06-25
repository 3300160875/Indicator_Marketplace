<?php
declare(strict_types=1);

namespace StockResource\Core\Version;

final readonly class ResourceVersionWorkflow
{
    /**
     * @param array<string, list<ResourceVersionStatus>> $transitions
     * @param array<string, ResourceVersionWorkflowStage> $stages
     */
    private function __construct(
        private array $transitions,
        private array $stages,
    ) {
    }

    public static function defaults(): self
    {
        return new self(
            transitions: [
                ResourceVersionStatus::Draft->value => [ResourceVersionStatus::Scanning],
                ResourceVersionStatus::Scanning->value => [ResourceVersionStatus::Review, ResourceVersionStatus::Draft],
                ResourceVersionStatus::Review->value => [ResourceVersionStatus::Active, ResourceVersionStatus::Scanning],
                ResourceVersionStatus::Active->value => [ResourceVersionStatus::Suspended, ResourceVersionStatus::Archived],
                ResourceVersionStatus::Suspended->value => [ResourceVersionStatus::Active, ResourceVersionStatus::Archived],
                ResourceVersionStatus::Archived->value => [],
            ],
            stages: [
                'upload' => new ResourceVersionWorkflowStage('upload', retryable: true, usesTransactionLock: false),
                'scan' => new ResourceVersionWorkflowStage('scan', retryable: true, usesTransactionLock: false),
                'review' => new ResourceVersionWorkflowStage('review', retryable: true, usesTransactionLock: false),
                'activate' => new ResourceVersionWorkflowStage('activate', retryable: true, usesTransactionLock: true),
            ],
        );
    }

    public function canTransition(ResourceVersionStatus $from, ResourceVersionStatus $to): bool
    {
        return in_array($to, $this->transitions[$from->value] ?? [], true);
    }

    /**
     * @return array<string, ResourceVersionWorkflowStage>
     */
    public function stages(): array
    {
        return $this->stages;
    }
}
