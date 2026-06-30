<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Audit;

final readonly class AuditLogQuery
{
    public function __construct(
        public ?string $requestId = null,
        public ?string $action = null,
        public ?string $subjectType = null,
        public int|string|null $subjectId = null,
        public ?bool $highRisk = null,
        public int $limit = 100,
    ) {
        if ($requestId !== null && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $requestId) !== 1) {
            throw new AuditException('invalid_request_id', 'request_id must be a UUID.');
        }
        if ($limit < 1 || $limit > 500) {
            throw new AuditException('invalid_limit', 'limit must be between 1 and 500.');
        }
    }

    public function matches(AuditLogRecord $record): bool
    {
        if ($this->requestId !== null && $record->requestId !== $this->requestId) {
            return false;
        }
        if ($this->action !== null && $record->action !== $this->action) {
            return false;
        }
        if ($this->subjectType !== null && $record->subjectType !== $this->subjectType) {
            return false;
        }
        if ($this->subjectId !== null && (string) $record->subjectId !== (string) $this->subjectId) {
            return false;
        }
        if ($this->highRisk !== null && $record->highRisk !== $this->highRisk) {
            return false;
        }

        return true;
    }
}
