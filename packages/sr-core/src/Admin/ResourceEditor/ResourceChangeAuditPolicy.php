<?php
declare(strict_types=1);

namespace StockResource\Core\Admin\ResourceEditor;

use StockResource\Core\Support\Audit\AuditEvent;

final readonly class ResourceChangeAuditPolicy
{
    /**
     * @param list<string> $highRiskFields
     */
    private function __construct(private array $highRiskFields)
    {
    }

    public static function defaults(): self
    {
        return new self([
            '_sr_access_mode',
            '_sr_current_version_id',
            '_sr_rights_status',
            '_sr_rights_record_id',
            '_sr_risk_level',
            '_sr_disclaimer_version',
            '_sr_featured',
            '_sr_sort_weight',
        ]);
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function auditEventForChanges(
        int $resourceId,
        string $actorType,
        int|string|null $actorId,
        string $requestId,
        array $before,
        array $after,
    ): ?AuditEvent {
        $changed = [];
        foreach ($this->highRiskFields as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changed[] = $field;
            }
        }

        if ($changed === []) {
            return null;
        }

        return new AuditEvent(
            action: 'resource_editor.high_risk_change',
            actorType: $actorType,
            actorId: $actorId,
            subjectType: 'resource',
            subjectId: $resourceId,
            requestId: $requestId,
            metadata: [
                'changed_fields' => $changed,
            ],
        );
    }
}
