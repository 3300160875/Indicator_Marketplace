<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$adminOps = $root.'/packages/sr-admin-ops';

foreach ([
    '/src/Auth/AuthorizationDecision.php',
    '/src/Auth/AuthorizationService.php',
    '/src/Auth/CapabilityDefinition.php',
    '/src/Auth/OwnedResourceSubject.php',
    '/src/Auth/RoleCapabilityMatrix.php',
    '/src/Auth/RoleDefinition.php',
    '/src/Auth/UserContext.php',
    '/src/Rights/RightsAuditEvent.php',
    '/src/Rights/RightsEvidenceAccessPolicy.php',
    '/src/Rights/RightsEvidencePolicy.php',
    '/src/Rights/RightsException.php',
    '/src/Rights/RightsExpiryDecision.php',
    '/src/Rights/RightsExpiryPolicy.php',
    '/src/Rights/RightsPublicationDecision.php',
    '/src/Rights/RightsPublicationGate.php',
    '/src/Rights/RightsPublicationRequest.php',
    '/src/Rights/RightsRecord.php',
] as $file) {
    require_once $adminOps.$file;
}

use StockResource\AdminOps\Auth\AuthorizationService;
use StockResource\AdminOps\Auth\RoleCapabilityMatrix;
use StockResource\AdminOps\Auth\UserContext;
use StockResource\AdminOps\Rights\RightsEvidenceAccessPolicy;
use StockResource\AdminOps\Rights\RightsEvidencePolicy;
use StockResource\AdminOps\Rights\RightsException;
use StockResource\AdminOps\Rights\RightsExpiryPolicy;
use StockResource\AdminOps\Rights\RightsPublicationGate;
use StockResource\AdminOps\Rights\RightsPublicationRequest;
use StockResource\AdminOps\Rights\RightsRecord;

function sr059_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr059_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr059_contains(string $needle, array $haystack, string $message): void
{
    if (! in_array($needle, $haystack, true)) {
        throw new RuntimeException($message.' missing='.$needle.' actual='.var_export($haystack, true));
    }
}

function sr059_record(array $overrides = []): RightsRecord
{
    return RightsRecord::fromArray(array_replace([
        'id' => 91,
        'resource_id' => 7001,
        'status' => 'approved',
        'source_type' => 'licensed',
        'rights_holder' => 'Example Rights Holder',
        'license_scope' => 'paid redistribution for subscribers',
        'license_reference' => 'license-2026-001',
        'evidence_storage_key' => 'private/rights/7001/license.pdf',
        'starts_at' => '2026-01-01T00:00:00+00:00',
        'expires_at' => '2026-07-15T00:00:00+00:00',
        'reviewer_id' => 42,
        'reviewed_at' => '2026-06-01T00:00:00+00:00',
        'internal_note' => 'reviewed',
        'created_at' => '2026-06-01T00:00:00+00:00',
        'updated_at' => '2026-06-01T00:00:00+00:00',
    ], $overrides));
}

$evidencePolicy = new RightsEvidencePolicy();
sr059_true($evidencePolicy->isPrivateStorageKey('private/rights/7001/license.pdf'), 'private rights storage key is accepted');
sr059_same(false, $evidencePolicy->isPrivateStorageKey('https://cdn.example/license.pdf'), 'public evidence URL is rejected');
try {
    sr059_record(['evidence_storage_key' => '../license.pdf']);
    throw new RuntimeException('path traversal evidence key should fail');
} catch (RightsException $exception) {
    sr059_same('evidence_storage_not_private', $exception->code(), 'unsafe evidence key has stable error code');
}
try {
    sr059_record(['evidence_storage_key' => '/var/www/license.pdf']);
    throw new RuntimeException('absolute evidence key should fail');
} catch (RightsException $exception) {
    sr059_same('evidence_storage_not_private', $exception->code(), 'absolute evidence key has stable error code');
}

$matrix = RoleCapabilityMatrix::defaults();
$accessPolicy = new RightsEvidenceAccessPolicy(new AuthorizationService($matrix));
$reviewer = UserContext::fromRoles(12, ['sr_compliance_reviewer'], $matrix);
$contractReviewer = new UserContext(14, ['sr_rights_reviewer'], ['view_sr_rights_evidence']);
$outsider = UserContext::fromRoles(13, ['sr_resource_editor'], $matrix);
$record = sr059_record();
sr059_same(['view_sr_rights_evidence', 'sr_review_rights_evidence'], $accessPolicy->capabilitySlugs(), 'rights evidence policy supports contract and current matrix capability names');
sr059_same(true, $accessPolicy->canViewEvidence($reviewer, $record, 12)->allowed, 'assigned rights reviewer can view private evidence');
sr059_same(true, $accessPolicy->canViewEvidence($contractReviewer, $record, 14)->allowed, 'contract rights reviewer capability can view owned private evidence');
sr059_same('not_resource_owner', $accessPolicy->canViewEvidence($contractReviewer, $record, 99)->reason, 'contract rights reviewer remains row-level owner scoped');
sr059_same(false, $accessPolicy->canViewEvidence($outsider, $record, 12)->allowed, 'resource editor cannot view private evidence');

$gate = new RightsPublicationGate(
    new RightsEvidencePolicy(),
    RightsExpiryPolicy::fromConfig(['warning_lead_days' => 10, 'expired_action' => 'pause_publication']),
);

$approved = $gate->evaluate(new RightsPublicationRequest(
    resourceId: 7001,
    accessMode: 'purchase',
    rightsStatus: 'approved',
    rightsRecordId: 91,
    resourceTakenDown: false,
    requestId: '11111111-1111-4111-8111-111111111111',
    actorId: 42,
    nowUtc: '2026-06-30T00:00:00+00:00',
), $record);
sr059_same(true, $approved->canPublish, 'approved paid resource can publish');
sr059_same(true, $approved->canIssueNewTokens, 'approved paid resource can issue new tokens');
sr059_same([], $approved->issueCodes(), 'approved paid resource has no blocking issues');

$pending = $gate->evaluate(new RightsPublicationRequest(
    resourceId: 7001,
    accessMode: 'purchase',
    rightsStatus: 'pending',
    rightsRecordId: 0,
    resourceTakenDown: false,
    requestId: '22222222-2222-4222-8222-222222222222',
    actorId: 42,
    nowUtc: '2026-06-30T00:00:00+00:00',
), null);
sr059_same(false, $pending->canPublish, 'pending paid resource cannot publish');
sr059_same(false, $pending->canIssueNewTokens, 'pending paid resource cannot issue new tokens');
sr059_contains('rights_approval_required', $pending->issueCodes(), 'pending paid resource requires approval');
sr059_contains('rights_record_required', $pending->issueCodes(), 'paid resource requires a rights record');
sr059_true($pending->auditEvents !== [], 'blocked paid resource emits audit event');

$mismatched = $gate->evaluate(new RightsPublicationRequest(
    resourceId: 7001,
    accessMode: 'purchase',
    rightsStatus: 'approved',
    rightsRecordId: 92,
    resourceTakenDown: false,
    requestId: '23232323-2323-4323-8323-232323232323',
    actorId: 42,
    nowUtc: '2026-06-30T00:00:00+00:00',
), sr059_record(['id' => 91, 'resource_id' => 7002]));
sr059_same(false, $mismatched->canPublish, 'mismatched rights record cannot publish');
sr059_contains('rights_record_resource_mismatch', $mismatched->issueCodes(), 'mismatched resource is blocked');
sr059_contains('rights_record_id_mismatch', $mismatched->issueCodes(), 'mismatched record id is blocked');

$expiring = $gate->evaluate(new RightsPublicationRequest(
    resourceId: 7001,
    accessMode: 'purchase_or_vip',
    rightsStatus: 'approved',
    rightsRecordId: 91,
    resourceTakenDown: false,
    requestId: '33333333-3333-4333-8333-333333333333',
    actorId: 42,
    nowUtc: '2026-07-10T00:00:00+00:00',
), $record);
sr059_same(true, $expiring->canPublish, 'expiring but still active rights can publish');
sr059_contains('rights_expiring', $expiring->warningCodes(), 'expiring rights emits configurable warning');

$expired = $gate->evaluate(new RightsPublicationRequest(
    resourceId: 7001,
    accessMode: 'vip',
    rightsStatus: 'approved',
    rightsRecordId: 91,
    resourceTakenDown: false,
    requestId: '44444444-4444-4444-8444-444444444444',
    actorId: 42,
    nowUtc: '2026-07-20T00:00:00+00:00',
), $record);
sr059_same(false, $expired->canPublish, 'expired rights pause paid publication');
sr059_same(false, $expired->canIssueNewTokens, 'expired rights block new token issuance');
sr059_contains('rights_expired', $expired->issueCodes(), 'expired rights has stable issue code');

$warnOnlyGate = new RightsPublicationGate(
    new RightsEvidencePolicy(),
    RightsExpiryPolicy::fromConfig(['warning_lead_days' => 10, 'expired_action' => 'warn_only']),
);
$warnOnlyExpired = $warnOnlyGate->evaluate(new RightsPublicationRequest(
    resourceId: 7001,
    accessMode: 'vip',
    rightsStatus: 'approved',
    rightsRecordId: 91,
    resourceTakenDown: false,
    requestId: '45454545-4545-4545-8545-454545454545',
    actorId: 42,
    nowUtc: '2026-07-20T00:00:00+00:00',
), $record);
sr059_same(true, $warnOnlyExpired->canPublish, 'warn-only expiry action does not pause publication');
sr059_same(true, $warnOnlyExpired->canIssueNewTokens, 'warn-only expiry action does not pause new token issuance');
sr059_true(! in_array('rights_expired', $warnOnlyExpired->issueCodes(), true), 'warn-only expiry action does not add pause issue code');
sr059_contains('rights_expired', $warnOnlyExpired->warningCodes(), 'warn-only expiry action still emits expiry warning');

$takenDown = $gate->evaluate(new RightsPublicationRequest(
    resourceId: 7001,
    accessMode: 'purchase',
    rightsStatus: 'approved',
    rightsRecordId: 91,
    resourceTakenDown: true,
    requestId: '55555555-5555-4555-8555-555555555555',
    actorId: 42,
    nowUtc: '2026-06-30T00:00:00+00:00',
), $record);
sr059_same(false, $takenDown->canPublish, 'taken-down resource cannot publish');
sr059_same(false, $takenDown->canIssueNewTokens, 'taken-down resource immediately blocks new tokens');
sr059_contains('resource_taken_down', $takenDown->issueCodes(), 'taken-down resource has stable issue code');
sr059_same('rights.publication_blocked', $takenDown->auditEvents[0]->eventName, 'taken-down block preserves audit event');
sr059_true(! str_contains(json_encode($takenDown->auditEvents[0]->payload, JSON_THROW_ON_ERROR), 'private/rights/7001/license.pdf'), 'audit event does not expose evidence storage key');

echo "SR-059 rights gate checks passed\n";
