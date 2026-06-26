<?php

declare(strict_types=1);

use StockResource\AdminOps\Auth\AuthorizationService;
use StockResource\AdminOps\Auth\OwnedResourceSubject;
use StockResource\AdminOps\Auth\RoleCapabilityMatrix;
use StockResource\AdminOps\Auth\UserContext;

$root = dirname(__DIR__, 3);
$adminOps = $root.'/packages/sr-admin-ops';

foreach ([
    '/src/Auth/CapabilityDefinition.php',
    '/src/Auth/RoleDefinition.php',
    '/src/Auth/RoleCapabilityMatrix.php',
    '/src/Auth/UserContext.php',
    '/src/Auth/OwnedResourceSubject.php',
    '/src/Auth/AuthorizationDecision.php',
    '/src/Auth/AuthorizationService.php',
] as $sourceFile) {
    require_once $adminOps.$sourceFile;
}

function sr027_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr027_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

$matrix = RoleCapabilityMatrix::defaults();
$roleSlugs = array_keys($matrix->roles());
sr027_same([
    'sr_resource_editor',
    'sr_technical_reviewer',
    'sr_finance_operator',
    'sr_customer_support',
    'sr_compliance_reviewer',
    'sr_operations_manager',
    'administrator',
], $roleSlugs, 'role matrix separates editor, technical, finance, support, compliance and operations roles');

$customRoles = array_filter($matrix->roles(), static fn ($role): bool => $role->slug !== 'administrator');
foreach ($customRoles as $role) {
    sr027_assert($role->capabilities !== [], $role->slug.' has explicit capabilities');
    foreach ($role->capabilities as $capability) {
        sr027_assert(! $matrix->capability($capability)->highRisk, $role->slug.' never receives high-risk capability '.$capability);
    }
}

$administrator = $matrix->role('administrator');
foreach ($matrix->highRiskCapabilities() as $capability) {
    sr027_assert(in_array($capability, $administrator->capabilities, true), 'administrator receives high-risk capability '.$capability);
}
sr027_assert(! in_array('sr_override_compliance_gate', $matrix->role('sr_operations_manager')->capabilities, true), 'operations role cannot override compliance gate');
sr027_assert(! in_array('sr_manage_capabilities', $matrix->role('sr_compliance_reviewer')->capabilities, true), 'compliance role cannot manage capabilities');

$auth = new AuthorizationService($matrix);
$ownResource = new OwnedResourceSubject(resourceId: 501, ownerUserId: 10);
$otherResource = new OwnedResourceSubject(resourceId: 502, ownerUserId: 20);

$editor = UserContext::fromRoles(userId: 10, roles: ['sr_resource_editor'], matrix: $matrix);
$technical = UserContext::fromRoles(userId: 10, roles: ['sr_technical_reviewer'], matrix: $matrix);
$finance = UserContext::fromRoles(userId: 30, roles: ['sr_finance_operator'], matrix: $matrix);
$admin = UserContext::fromRoles(userId: 1, roles: ['administrator'], matrix: $matrix);

$ownEdit = $auth->can($editor, 'sr_edit_resource_content', $ownResource);
sr027_same(true, $ownEdit->allowed, 'editor can edit owned resource content');

$foreignEdit = $auth->can($editor, 'sr_edit_resource_content', $otherResource);
sr027_same(false, $foreignEdit->allowed, 'editor cannot edit another owner resource');
sr027_same('not_resource_owner', $foreignEdit->reason, 'foreign resource denial records ownership reason');

$technicalContentEdit = $auth->can($technical, 'sr_edit_resource_content', $ownResource);
sr027_same(false, $technicalContentEdit->allowed, 'technical role cannot edit resource content without capability');
sr027_same('missing_capability', $technicalContentEdit->reason, 'missing capability denial is explicit');

$financeOverride = $auth->can($finance, 'sr_override_compliance_gate', $ownResource);
sr027_same(false, $financeOverride->allowed, 'finance role cannot use high-risk compliance override');
sr027_same('high_risk_requires_administrator', $financeOverride->reason, 'high-risk denial is explicit');

$adminOverride = $auth->can($admin, 'sr_override_compliance_gate', $otherResource);
sr027_same(true, $adminOverride->allowed, 'administrator can use high-risk capability on any resource');

$financeReport = $auth->can($finance, 'sr_view_revenue_reports');
sr027_same(true, $financeReport->allowed, 'finance role can view revenue reports');
sr027_same(false, $auth->can($editor, 'sr_view_revenue_reports')->allowed, 'editor role cannot view revenue reports');

echo "SR-027 auth checks passed.\n";
