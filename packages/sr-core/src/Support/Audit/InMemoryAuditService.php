<?php
declare(strict_types=1);

namespace StockResource\Core\Support\Audit;

use StockResource\Core\Support\Logging\SensitiveFieldRedactor;

final class InMemoryAuditService implements AuditService
{
    /** @var list<AuditEvent> */
    private array $events = [];

    private SensitiveFieldRedactor $redactor;

    public function __construct(?SensitiveFieldRedactor $redactor = null)
    {
        $this->redactor = $redactor ?? new SensitiveFieldRedactor();
    }

    public function record(AuditEvent $event): void
    {
        $this->events[] = $event->withMetadata($this->redactor->redact($event->metadata));
    }

    /**
     * @return list<AuditEvent>
     */
    public function events(): array
    {
        return $this->events;
    }
}
