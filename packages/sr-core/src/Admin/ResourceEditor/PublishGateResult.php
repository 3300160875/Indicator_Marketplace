<?php
declare(strict_types=1);

namespace StockResource\Core\Admin\ResourceEditor;

final readonly class PublishGateResult
{
    /** @param list<GateIssue> $issues */
    public function __construct(private array $issues)
    {
    }

    public function canPublish(): bool
    {
        return $this->issues === [];
    }

    /**
     * @return list<GateIssue>
     */
    public function issues(): array
    {
        return $this->issues;
    }

    /**
     * @return list<string>
     */
    public function issueCodes(): array
    {
        return array_map(static fn(GateIssue $issue): string => $issue->code, $this->issues);
    }
}
