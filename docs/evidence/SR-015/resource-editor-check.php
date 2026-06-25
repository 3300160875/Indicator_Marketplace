<?php
declare(strict_types=1);

use StockResource\Core\Admin\ResourceEditor\ResourceChangeAuditPolicy;
use StockResource\Core\Admin\ResourceEditor\ResourceDraft;
use StockResource\Core\Admin\ResourceEditor\ResourceEditorSectionCatalog;
use StockResource\Core\Admin\ResourceEditor\ResourcePublishGate;

$root = dirname(__DIR__, 3);
$files = [
    '/packages/sr-core/src/Support/Audit/AuditEvent.php',
    '/packages/sr-core/src/Admin/ResourceEditor/EditorSection.php',
    '/packages/sr-core/src/Admin/ResourceEditor/ResourceEditorSectionCatalog.php',
    '/packages/sr-core/src/Admin/ResourceEditor/GateIssue.php',
    '/packages/sr-core/src/Admin/ResourceEditor/PublishGateResult.php',
    '/packages/sr-core/src/Admin/ResourceEditor/ResourceDraft.php',
    '/packages/sr-core/src/Admin/ResourceEditor/ResourcePublishGate.php',
    '/packages/sr-core/src/Admin/ResourceEditor/ResourceChangeAuditPolicy.php',
];

foreach ($files as $file) {
    require_once $root . $file;
}

function sr_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

$sections = ResourceEditorSectionCatalog::defaults();
$requiredSections = ['editorial', 'technical', 'rights', 'commercial'];
foreach ($requiredSections as $requiredSection) {
    sr_assert(isset($sections->sections()[$requiredSection]), 'resource editor contains required section: ' . $requiredSection);
}
sr_assert(in_array('post_title', $sections->get('editorial')->fields(), true), 'editorial section contains title');
sr_assert(in_array('_sr_future_function_status', $sections->get('technical')->fields(), true), 'technical section contains future function status');
sr_assert(in_array('_sr_rights_status', $sections->get('rights')->fields(), true), 'rights section contains rights status');
sr_assert(in_array('_sr_access_mode', $sections->get('commercial')->fields(), true), 'commercial section contains access mode');
sr_assert($sections->sectionFor('_sr_sort_weight')?->key() === 'operations', 'operations extension section owns sort weight');

$publishable = ResourceDraft::fromArray([
    'resource_id' => 1001,
    'post_title' => '通达信趋势指标',
    'post_excerpt' => '用于趋势识别的指标资源。',
    'post_content' => '<p>安装后用于辅助趋势观察，不构成投资建议。</p>',
    'screenshot_count' => 1,
    'price_configured' => true,
    'taxonomies' => [
        'download_category' => ['vip-formula'],
        'sr_platform' => ['tongdaxin'],
        'sr_indicator_type' => ['sub-chart'],
        'sr_content_type' => ['indicator'],
    ],
    'meta' => [
        '_sr_access_mode' => 'purchase',
        '_sr_software_versions' => ['通达信 7.60'],
        '_sr_device' => 'desktop',
        '_sr_os' => 'windows',
        '_sr_file_format' => 'tn6',
        '_sr_charset' => 'gbk',
        '_sr_source_included' => 'yes',
        '_sr_future_function_status' => 'none',
        '_sr_l2_required' => 'no',
        '_sr_install_steps' => '<p>导入公式管理器。</p>',
        '_sr_usage_scenarios' => '<p>趋势观察。</p>',
        '_sr_limitations' => '<p>仅辅助判断。</p>',
        '_sr_current_version_id' => 88,
        '_sr_rights_status' => 'approved',
        '_sr_rights_record_id' => 32,
        '_sr_risk_level' => 'medium',
        '_sr_disclaimer_version' => 'risk-v1',
    ],
]);
$gate = new ResourcePublishGate();
$publishableResult = $gate->evaluate($publishable);
sr_same(true, $publishableResult->canPublish(), 'complete resource draft can publish');
sr_same([], $publishableResult->issueCodes(), 'complete resource has no publish gate issues');

$blocked = ResourceDraft::fromArray([
    'resource_id' => 1002,
    'post_title' => '稳赚抓牛股指标',
    'post_excerpt' => '',
    'post_content' => '<p>缺少安装和风险说明。</p>',
    'screenshot_count' => 0,
    'price_configured' => false,
    'taxonomies' => [
        'download_category' => [],
        'sr_platform' => [],
        'sr_indicator_type' => [],
        'sr_content_type' => [],
    ],
    'meta' => [
        '_sr_access_mode' => 'unavailable',
        '_sr_software_versions' => [],
        '_sr_device' => 'unknown',
        '_sr_os' => 'unknown',
        '_sr_file_format' => 'other',
        '_sr_charset' => 'unknown',
        '_sr_source_included' => 'unknown',
        '_sr_future_function_status' => 'unknown',
        '_sr_l2_required' => 'unknown',
        '_sr_install_steps' => '',
        '_sr_current_version_id' => null,
        '_sr_rights_status' => 'pending',
        '_sr_risk_level' => 'blocked',
        '_sr_disclaimer_version' => '',
    ],
]);
$blockedResult = $gate->evaluate($blocked);
sr_same(false, $blockedResult->canPublish(), 'P0 missing fields block publishing');
$issueCodes = $blockedResult->issueCodes();
sort($issueCodes);
sr_same([
    'access_mode_required',
    'category_required',
    'compatibility_required',
    'current_version_required',
    'disclaimer_required',
    'future_function_verification_required',
    'install_steps_required',
    'l2_requirement_required',
    'limitations_required',
    'prohibited_claim',
    'rights_approval_required',
    'risk_blocked',
    'screenshot_required',
    'summary_required',
    'usage_scenarios_required',
], $issueCodes, 'publish gate returns stable P0 issue codes');

$paidWithoutPrice = ResourceDraft::fromArray([
    'resource_id' => 1003,
    'post_title' => '通达信趋势指标',
    'post_excerpt' => '用于趋势识别的指标资源。',
    'post_content' => '<p>安装后用于辅助趋势观察，不构成投资建议。</p>',
    'screenshot_count' => 1,
    'price_configured' => false,
    'taxonomies' => [
        'download_category' => ['vip-formula'],
        'sr_platform' => ['tongdaxin'],
        'sr_indicator_type' => ['sub-chart'],
        'sr_content_type' => ['indicator'],
    ],
    'meta' => [
        '_sr_access_mode' => 'purchase',
        '_sr_software_versions' => ['通达信 7.60'],
        '_sr_device' => 'desktop',
        '_sr_os' => 'windows',
        '_sr_file_format' => 'tn6',
        '_sr_charset' => 'gbk',
        '_sr_source_included' => 'yes',
        '_sr_future_function_status' => 'none',
        '_sr_l2_required' => 'no',
        '_sr_install_steps' => '<p>导入公式管理器。</p>',
        '_sr_usage_scenarios' => '<p>趋势观察。</p>',
        '_sr_limitations' => '<p>仅辅助判断。</p>',
        '_sr_current_version_id' => 88,
        '_sr_rights_status' => 'approved',
        '_sr_rights_record_id' => 32,
        '_sr_risk_level' => 'medium',
        '_sr_disclaimer_version' => 'risk-v1',
    ],
]);
sr_same(['price_required'], $gate->evaluate($paidWithoutPrice)->issueCodes(), 'paid resources require EDD price configuration');

$incompleteTechnical = ResourceDraft::fromArray([
    'resource_id' => 1004,
    'post_title' => '通达信趋势指标',
    'post_excerpt' => '用于趋势识别的指标资源。',
    'post_content' => '<p>安装后用于辅助趋势观察，不构成投资建议。</p>',
    'screenshot_count' => 1,
    'price_configured' => true,
    'taxonomies' => [
        'download_category' => ['vip-formula'],
        'sr_platform' => ['tongdaxin'],
        'sr_indicator_type' => ['sub-chart'],
        'sr_content_type' => ['indicator'],
    ],
    'meta' => [
        '_sr_access_mode' => 'purchase',
        '_sr_software_versions' => ['通达信 7.60'],
        '_sr_device' => 'desktop',
        '_sr_os' => 'windows',
        '_sr_file_format' => 'other',
        '_sr_charset' => 'gbk',
        '_sr_source_included' => 'unknown',
        '_sr_future_function_status' => 'none',
        '_sr_l2_required' => 'no',
        '_sr_install_steps' => '<p>导入公式管理器。</p>',
        '_sr_usage_scenarios' => '',
        '_sr_limitations' => '',
        '_sr_current_version_id' => 88,
        '_sr_rights_status' => 'approved',
        '_sr_rights_record_id' => 32,
        '_sr_risk_level' => 'medium',
        '_sr_disclaimer_version' => 'risk-v1',
    ],
]);
$technicalIssueCodes = $gate->evaluate($incompleteTechnical)->issueCodes();
sort($technicalIssueCodes);
sr_same([
    'compatibility_required',
    'limitations_required',
    'usage_scenarios_required',
], $technicalIssueCodes, 'technical compatibility, usage scenarios and limitations are required');

$paidWithoutRightsRecord = ResourceDraft::fromArray([
    'resource_id' => 1005,
    'post_title' => '通达信趋势指标',
    'post_excerpt' => '用于趋势识别的指标资源。',
    'post_content' => '<p>安装后用于辅助趋势观察，不构成投资建议。</p>',
    'screenshot_count' => 1,
    'price_configured' => true,
    'taxonomies' => [
        'download_category' => ['vip-formula'],
        'sr_platform' => ['tongdaxin'],
        'sr_indicator_type' => ['sub-chart'],
        'sr_content_type' => ['indicator'],
    ],
    'meta' => [
        '_sr_access_mode' => 'purchase',
        '_sr_software_versions' => ['通达信 7.60'],
        '_sr_device' => 'desktop',
        '_sr_os' => 'windows',
        '_sr_file_format' => 'tn6',
        '_sr_charset' => 'gbk',
        '_sr_source_included' => 'yes',
        '_sr_future_function_status' => 'none',
        '_sr_l2_required' => 'no',
        '_sr_install_steps' => '<p>导入公式管理器。</p>',
        '_sr_usage_scenarios' => '<p>趋势观察。</p>',
        '_sr_limitations' => '<p>仅辅助判断。</p>',
        '_sr_current_version_id' => 88,
        '_sr_rights_status' => 'approved',
        '_sr_rights_record_id' => 0,
        '_sr_risk_level' => 'medium',
        '_sr_disclaimer_version' => 'risk-v1',
    ],
]);
sr_same(['rights_record_required'], $gate->evaluate($paidWithoutRightsRecord)->issueCodes(), 'paid resources require a rights evidence record');

$policy = ResourceChangeAuditPolicy::defaults();
$auditEvent = $policy->auditEventForChanges(
    resourceId: 1001,
    actorType: 'user',
    actorId: 501,
    requestId: '123e4567-e89b-42d3-a456-426614174000',
    before: [
        '_sr_access_mode' => 'free',
        '_sr_rights_status' => 'pending',
        '_sr_install_steps' => '<p>Old</p>',
    ],
    after: [
        '_sr_access_mode' => 'purchase',
        '_sr_rights_status' => 'approved',
        '_sr_install_steps' => '<p>New</p>',
    ],
);
sr_assert($auditEvent !== null, 'high-risk field changes produce an audit event');
sr_same('resource_editor.high_risk_change', $auditEvent->action, 'audit action is stable');
sr_same(['_sr_access_mode', '_sr_rights_status'], $auditEvent->metadata['changed_fields'], 'audit event only includes high-risk changed fields');
sr_same(1001, $auditEvent->subjectId, 'audit event subject is the resource');

$noAuditEvent = $policy->auditEventForChanges(
    resourceId: 1001,
    actorType: 'user',
    actorId: 501,
    requestId: '123e4567-e89b-42d3-a456-426614174000',
    before: ['_sr_install_steps' => '<p>Old</p>'],
    after: ['_sr_install_steps' => '<p>New</p>'],
);
sr_same(null, $noAuditEvent, 'low-risk content-only changes do not produce high-risk audit event');

echo "SR-015 resource editor check: ok\n";
