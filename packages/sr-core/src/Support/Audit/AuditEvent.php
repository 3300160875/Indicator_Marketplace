<?php
declare(strict_types=1);

namespace StockResource\Core\Support\Audit;

final readonly class AuditEvent
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $action,
        public string $actorType,
        public int|string|null $actorId,
        public string $subjectType,
        public int|string $subjectId,
        public string $requestId,
        public array $metadata = [],
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            action: $this->action,
            actorType: $this->actorType,
            actorId: $this->actorId,
            subjectType: $this->subjectType,
            subjectId: $this->subjectId,
            requestId: $this->requestId,
            metadata: $metadata,
        );
    }
}
