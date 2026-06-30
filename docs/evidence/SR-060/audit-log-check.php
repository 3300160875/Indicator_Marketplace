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
    '/src/Audit/AuditActionCatalog.php',
    '/src/Audit/AuditDeletePolicy.php',
    '/src/Audit/AuditException.php',
    '/src/Audit/AuditLogQuery.php',
    '/src/Audit/AuditLogRecord.php',
    '/src/Audit/AuditLogRepository.php',
    '/src/Audit/AuditLogSchema.php',
    '/src/Audit/AuditLogService.php',
    '/src/Audit/AuditQueryService.php',
    '/src/Audit/AuditQueryView.php',
    '/src/Audit/AuditRedactor.php',
    '/src/Audit/InMemoryAuditLogRepository.php',
] as $file) {
    require_once $adminOps.$file;
}

use StockResource\AdminOps\Audit\AuditActionCatalog;
use StockResource\AdminOps\Audit\AuditDeletePolicy;
use StockResource\AdminOps\Audit\AuditException;
use StockResource\AdminOps\Audit\AuditLogQuery;
use StockResource\AdminOps\Audit\AuditLogSchema;
use StockResource\AdminOps\Audit\AuditLogService;
use StockResource\AdminOps\Audit\AuditQueryView;
use StockResource\AdminOps\Audit\AuditQueryService;
use StockResource\AdminOps\Audit\AuditRedactor;
use StockResource\AdminOps\Audit\InMemoryAuditLogRepository;
use StockResource\AdminOps\Auth\RoleCapabilityMatrix;
use StockResource\AdminOps\Auth\UserContext;

function sr060_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr060_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$catalog = AuditActionCatalog::defaults();
sr060_true($catalog->isHighRisk('payment.approved'), 'payment approvals are high risk');
sr060_true($catalog->isHighRisk('entitlement.revoked'), 'revocations are high risk');
sr060_true($catalog->isHighRisk('resource.published'), 'publication is high risk');
sr060_true($catalog->isHighRisk('config.changed'), 'configuration changes are high risk');

$schemaSql = AuditLogSchema::createSql('wp_');
sr060_true(str_contains($schemaSql, 'CREATE TABLE wp_sr_audit_logs'), 'audit persistence table SQL is defined');
sr060_true(str_contains($schemaSql, 'object_type VARCHAR(64) NOT NULL'), 'audit schema uses contract object_type field');
sr060_true(str_contains($schemaSql, 'after_json LONGTEXT NULL'), 'audit schema uses contract after_json field');
sr060_true(str_contains($schemaSql, 'KEY idx_request (request_id)'), 'audit persistence SQL indexes request_id');
sr060_true(! str_contains($schemaSql, 'subject_type'), 'audit schema does not drift to subject_type field');
sr060_true(! str_contains($schemaSql, 'metadata_json'), 'audit schema does not drift to metadata_json field');
sr060_same(['idx_actor', 'idx_object', 'idx_action_time', 'idx_request'], AuditLogSchema::requiredIndexes(), 'audit persistence indexes match contract');

$redacted = (new AuditRedactor())->redact([
    'request_id' => '11111111-1111-4111-8111-111111111111',
    'payment_proof_url' => 'https://cdn.example/proof.jpg',
    'authorization' => 'Bearer raw',
    'nested' => [
        'token_hash' => 'secret',
        'storage_key' => 'private/proofs/one.jpg',
        'safe' => 'kept',
    ],
]);
sr060_same('[REDACTED]', $redacted['payment_proof_url'], 'payment proof URL is redacted');
sr060_same('[REDACTED]', $redacted['authorization'], 'authorization header is redacted');
sr060_same('[REDACTED]', $redacted['nested']['token_hash'], 'nested token hash is redacted');
sr060_same('[REDACTED]', $redacted['nested']['storage_key'], 'nested storage key is redacted');
sr060_same('kept', $redacted['nested']['safe'], 'safe metadata is preserved');

$repository = new InMemoryAuditLogRepository();
$service = new AuditLogService($repository, $catalog, new AuditRedactor());
try {
    $service->record(
        action: 'unknown.action',
        actorType: 'user',
        actorId: 501,
        subjectType: 'payment_submission',
        subjectId: 9001,
        requestId: '12121212-1212-4212-8212-121212121212',
        occurredAt: '2026-06-30T00:00:00+00:00',
    );
    throw new RuntimeException('unknown audit action should fail');
} catch (AuditException $exception) {
    sr060_same('unknown_audit_action', $exception->code(), 'unknown actions are rejected');
}

$service->record(
    action: 'payment.approved',
    actorType: 'user',
    actorId: 501,
    subjectType: 'payment_submission',
    subjectId: 9001,
    requestId: '11111111-1111-4111-8111-111111111111',
    occurredAt: '2026-06-30T00:00:00+00:00',
    metadata: ['proof_storage_key' => 'private/payment/9001.jpg', 'amount' => '99.00'],
);
$service->record(
    action: 'entitlement.revoked',
    actorType: 'user',
    actorId: 502,
    subjectType: 'entitlement',
    subjectId: 8001,
    requestId: '22222222-2222-4222-8222-222222222222',
    occurredAt: '2026-06-30T00:01:00+00:00',
    metadata: ['reason_code' => 'refund'],
);
$service->record(
    action: 'resource.published',
    actorType: 'user',
    actorId: 503,
    subjectType: 'resource',
    subjectId: 7001,
    requestId: '33333333-3333-4333-8333-333333333333',
    occurredAt: '2026-06-30T00:02:00+00:00',
    metadata: ['rights_status' => 'approved'],
);
$service->record(
    action: 'config.changed',
    actorType: 'user',
    actorId: 1,
    subjectType: 'feature_flag',
    subjectId: 'SR_PAID_DOWNLOADS_ENABLED',
    requestId: '44444444-4444-4444-8444-444444444444',
    occurredAt: '2026-06-30T00:03:00+00:00',
    metadata: ['previous_value' => false, 'new_value' => true, 'api_secret' => 'raw-secret'],
);
$service->record(
    action: 'download.failed',
    actorType: 'system',
    actorId: null,
    subjectType: 'download_event',
    subjectId: 6001,
    requestId: '55555555-5555-4555-8555-555555555555',
    occurredAt: '2026-06-30T00:04:00+00:00',
    metadata: ['failure_code' => 'storage_missing'],
);

sr060_same(5, $repository->count(), 'audit logs are appended');
sr060_same('[REDACTED]', $repository->all()[0]->metadata['proof_storage_key'], 'stored metadata is redacted');
sr060_same('[REDACTED]', $repository->all()[3]->metadata['api_secret'], 'config secrets are redacted');
sr060_same(false, $repository->all()[4]->highRisk, 'download failed audit is not high risk');

try {
    (new ReflectionClass($repository))->getMethod('delete');
    throw new RuntimeException('audit repository must not expose delete');
} catch (ReflectionException) {
    sr060_true(true, 'audit repository does not expose delete');
}

$matrix = RoleCapabilityMatrix::defaults();
$admin = UserContext::fromRoles(1, ['administrator'], $matrix);
$auditAdmin = new UserContext(1, ['administrator'], ['view_sr_audit_logs']);
$ops = UserContext::fromRoles(2, ['sr_operations_manager'], $matrix);
$support = UserContext::fromRoles(3, ['sr_customer_support'], $matrix);
$finance = UserContext::fromRoles(4, ['sr_finance_operator'], $matrix);

$deletePolicy = new AuditDeletePolicy();
sr060_same(false, $deletePolicy->canDelete($admin)->allowed, 'ordinary administrator cannot delete audit logs');
sr060_same('audit_delete_forbidden', $deletePolicy->canDelete($admin)->reason, 'delete denial has stable code');

$queryService = new AuditQueryService($repository);
try {
    new AuditLogQuery(limit: 501);
    throw new RuntimeException('oversized audit query limit should fail');
} catch (AuditException $exception) {
    sr060_same('invalid_limit', $exception->code(), 'oversized query limit is rejected');
}
$byRequest = $queryService->query($auditAdmin, new AuditLogQuery(requestId: '11111111-1111-4111-8111-111111111111'));
sr060_same(1, count($byRequest), 'query can filter by request_id');
sr060_same('payment.approved', $byRequest[0]->action, 'request_id query returns expected action');
sr060_same([], $queryService->query($admin, new AuditLogQuery(requestId: '11111111-1111-4111-8111-111111111111')), 'ordinary administrator cannot query audit without explicit audit capability');
sr060_same([], $queryService->query($ops, new AuditLogQuery(requestId: '11111111-1111-4111-8111-111111111111')), 'request_id does not bypass operations subject visibility');
sr060_same('payment.approved', $queryService->query($finance, new AuditLogQuery(requestId: '11111111-1111-4111-8111-111111111111'))[0]->action, 'finance can query payment audit by request_id');

$resourceOnly = $queryService->query($ops, new AuditLogQuery(subjectType: 'resource'));
sr060_same(1, count($resourceOnly), 'operations role can query resource audit');
sr060_same([], $queryService->query($support, new AuditLogQuery(subjectType: 'payment_submission')), 'support role cannot query payment audit details');
$supportDefault = $queryService->query($support, new AuditLogQuery());
sr060_same(1, count($supportDefault), 'support default query is scoped to download audit records');
sr060_same('download.failed', $supportDefault[0]->action, 'support scoped query returns download audit');
$view = AuditQueryView::page($byRequest, new AuditLogQuery(requestId: '11111111-1111-4111-8111-111111111111'));
sr060_same(1, $view['count'], 'query view exposes result count');
sr060_same('payment.approved', $view['data'][0]['action'], 'query view exposes audit rows');
sr060_same('[REDACTED]', $view['data'][0]['metadata']['proof_storage_key'], 'query view keeps redacted metadata');

echo "SR-060 audit log checks passed\n";
