<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Admin;

final readonly class WorkbenchActionDecision
{
    /**
     * @param array<string, mixed> $auditMetadata
     * @param list<array<string, mixed>> $auditRecords
     */
    private function __construct(
        public bool $allowed,
        public string $reason,
        public bool $requiresAudit,
        public ?string $auditAction,
        public array $auditMetadata,
        public array $auditRecords,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $auditRecords
     */
    public static function allow(string $auditAction, array $auditRecords): self
    {
        return new self(true, 'allowed', true, $auditAction, [
            'audit_record_count' => count($auditRecords),
        ], $auditRecords);
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason, false, null, [], []);
    }
}
