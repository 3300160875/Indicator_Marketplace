<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Rights;

final readonly class RightsPublicationDecision
{
    /**
     * @param list<string> $issues
     * @param list<string> $warnings
     * @param list<RightsAuditEvent> $auditEvents
     */
    public function __construct(
        public bool $canPublish,
        public bool $canIssueNewTokens,
        private array $issues,
        private array $warnings,
        public array $auditEvents,
    ) {
    }

    /**
     * @return list<string>
     */
    public function issueCodes(): array
    {
        return $this->issues;
    }

    /**
     * @return list<string>
     */
    public function warningCodes(): array
    {
        return $this->warnings;
    }
}
