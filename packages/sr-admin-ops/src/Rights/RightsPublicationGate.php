<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Rights;

final readonly class RightsPublicationGate
{
    public function __construct(
        private RightsEvidencePolicy $evidencePolicy,
        private RightsExpiryPolicy $expiryPolicy,
    ) {
    }

    public function evaluate(RightsPublicationRequest $request, ?RightsRecord $record): RightsPublicationDecision
    {
        $issues = [];
        $warnings = [];

        if ($request->resourceTakenDown) {
            $issues[] = 'resource_taken_down';
        }

        if ($request->isPaidAccessMode()) {
            if ($request->rightsStatus !== 'approved') {
                $issues[] = 'rights_approval_required';
            }

            if (($request->rightsRecordId ?? 0) <= 0) {
                $issues[] = 'rights_record_required';
            }

            if ($record === null) {
                $issues[] = 'rights_record_missing';
            } else {
                if ($record->resourceId !== $request->resourceId) {
                    $issues[] = 'rights_record_resource_mismatch';
                }
                if ($request->rightsRecordId !== null && $record->id !== $request->rightsRecordId) {
                    $issues[] = 'rights_record_id_mismatch';
                }
                if (! $record->isApproved()) {
                    $issues[] = 'rights_record_not_approved';
                }
                if (! $this->evidencePolicy->isPrivateStorageKey($record->evidenceStorageKey)) {
                    $issues[] = 'evidence_private_required';
                }
                if (! $record->hasStartedAt($request->nowUtc)) {
                    $issues[] = 'rights_not_effective';
                }

                $expiry = $this->expiryPolicy->evaluate($record, $request->nowUtc);
                if ($expiry->warningRequired) {
                    $warnings[] = $expiry->reasonCode;
                }
                if ($expiry->pauseRequired) {
                    $issues[] = 'rights_expired';
                }
            }
        }

        $issues = array_values(array_unique($issues));
        $warnings = array_values(array_unique($warnings));
        $blocked = $issues !== [];

        return new RightsPublicationDecision(
            canPublish: ! $blocked,
            canIssueNewTokens: ! $blocked,
            issues: $issues,
            warnings: $warnings,
            auditEvents: $blocked ? [$this->blockedEvent($request, $record, $issues, $warnings)] : [],
        );
    }

    /**
     * @param list<string> $issues
     * @param list<string> $warnings
     */
    private function blockedEvent(
        RightsPublicationRequest $request,
        ?RightsRecord $record,
        array $issues,
        array $warnings,
    ): RightsAuditEvent {
        return new RightsAuditEvent(
            eventName: 'rights.publication_blocked',
            aggregateType: 'resource',
            aggregateId: (string) $request->resourceId,
            requestId: $request->requestId,
            occurredAt: $request->nowUtc,
            actorId: $request->actorId,
            payload: [
                'resource_id' => $request->resourceId,
                'access_mode' => $request->accessMode,
                'rights_status' => $request->rightsStatus,
                'rights_record_id' => $record?->id ?? $request->rightsRecordId,
                'resource_taken_down' => $request->resourceTakenDown,
                'issue_codes' => $issues,
                'warning_codes' => $warnings,
            ],
        );
    }
}
