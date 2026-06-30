<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Audit;

final readonly class AuditActionCatalog
{
    /** @param array<string, bool> $actions */
    private function __construct(private array $actions)
    {
    }

    public static function defaults(): self
    {
        return new self([
            'payment.approved' => true,
            'payment.rejected' => true,
            'entitlement.revoked' => true,
            'entitlement.granted' => true,
            'resource.published' => true,
            'resource.unpublished' => true,
            'resource.taken_down' => true,
            'config.changed' => true,
            'rights.publication_blocked' => true,
            'download.failed' => false,
            'download.redirected' => false,
            'support.message_created' => false,
        ]);
    }

    public function knows(string $action): bool
    {
        return array_key_exists($action, $this->actions);
    }

    public function isHighRisk(string $action): bool
    {
        return $this->actions[$action] ?? false;
    }
}
