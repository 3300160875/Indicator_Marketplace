<?php
declare(strict_types=1);

use StockResource\Entitlements\Plan\MembershipPlanRules;
use StockResource\Entitlements\Plan\MembershipPlanMetaException;
use StockResource\Entitlements\Plan\MembershipPlanScopeType;
use StockResource\Entitlements\Plan\MembershipQuotaPeriod;
use StockResource\Entitlements\Plan\MembershipRedownloadPolicy;
use StockResource\Entitlements\Plan\MembershipPlanDurationUnit;

$root = dirname(__DIR__, 3);
$files = [
    '/packages/sr-entitlements/src/Plan/MembershipPlanMetaException.php',
    '/packages/sr-entitlements/src/Plan/PlanDuration.php',
    '/packages/sr-entitlements/src/Plan/PlanScope.php',
    '/packages/sr-entitlements/src/Plan/PlanQuota.php',
    '/packages/sr-entitlements/src/Plan/MembershipPlanRules.php',
];

foreach ($files as $file) {
    require_once $root . $file;
}

function sr044_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function sr044_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

function sr044_expect_exception(string $codeName, callable $callback): void
{
    try {
        $callback();
    } catch (MembershipPlanMetaException $exception) {
        if ($exception->codeName !== $codeName) {
            throw new RuntimeException(
                'Expected exception code '.$codeName.' but got '.$exception->codeName.' with message: '.$exception->getMessage(),
            );
        }

        return;
    } catch (RuntimeException $exception) {
        throw new RuntimeException('Expected MembershipPlanMetaException '.$codeName.' but got RuntimeException '.$exception->getMessage());
    }

    throw new RuntimeException('Expected exception '.$codeName.' but no exception was thrown');
}

$validMeta = [
    '_sr_product_type' => 'membership_plan',
    '_sr_plan_code' => 'vip_30d',
    '_sr_duration_value' => 30,
    '_sr_duration_unit' => 'day',
    '_sr_scope_type' => 'resources',
    '_sr_scope_rules_json' => json_encode(['resource_ids' => [1001, 1002]]),
    '_sr_excluded_resource_ids' => json_encode([99, 88]),
    '_sr_quota_period' => 'day',
    '_sr_quota_limit' => 20,
    '_sr_redownload_policy' => 'count_each',
    '_sr_rules_version' => 'p1.0',
    '_sr_plan_active' => 1,
    '_sr_priority' => 100,
];

$rules = MembershipPlanRules::fromMeta($validMeta);
sr044_same('vip_30d', $rules->planCode, 'plan code parsed');
sr044_same(30, $rules->duration->value, 'duration value parsed');
sr044_same(MembershipPlanDurationUnit::Day, $rules->duration->unit, 'duration unit parsed');
sr044_same(MembershipPlanScopeType::Resources, $rules->scope->type, 'scope type parsed');
sr044_same([88, 99], $rules->scope->excludedResourceIds, 'excluded ids are normalized and sorted');
sr044_same(MembershipQuotaPeriod::Day, $rules->quota->period, 'quota period parsed');
sr044_same(20, $rules->quota->limit, 'quota limit parsed');
sr044_same(MembershipRedownloadPolicy::CountEach, $rules->quota->redownloadPolicy, 'quota redownload policy parsed');
sr044_same('p1.0', $rules->rulesVersion, 'rules version parsed');
sr044_assert($rules->isSellable(), 'active plan is sellable');

$snapshot = $rules->toOrderTermsSnapshot();
sr044_same(4, count($snapshot), 'rules snapshot includes duration/scope/quota and rules_version');
sr044_same('day', $snapshot['duration']['unit'], 'snapshot keeps duration unit');
sr044_same(20, $snapshot['quota']['limit'], 'snapshot keeps quota limit');
sr044_same('count_each', $snapshot['quota']['redownload_policy'], 'snapshot keeps redownload policy');

sr044_expect_exception('unsupported_lifetime', fn () => MembershipPlanRules::fromMeta(array_replace(
    $validMeta,
    ['_sr_duration_unit' => 'lifetime'],
)));

sr044_expect_exception('invalid_positive_int', fn () => MembershipPlanRules::fromMeta(array_replace(
    $validMeta,
    ['_sr_quota_limit' => 0],
)));

sr044_expect_exception('invalid_scope_type', fn () => MembershipPlanRules::fromMeta(array_replace(
    $validMeta,
    ['_sr_scope_type' => 'invalid'],
)));

sr044_expect_exception('missing_rules_version', fn () => MembershipPlanRules::fromMeta([
    '_sr_plan_code' => 'vip_30d',
    '_sr_duration_value' => 30,
    '_sr_duration_unit' => 'day',
    '_sr_scope_type' => 'resources',
    '_sr_scope_rules_json' => json_encode(['resource_ids' => [1001, 1002]]),
    '_sr_excluded_resource_ids' => '[]',
    '_sr_quota_period' => 'day',
    '_sr_quota_limit' => 20,
    '_sr_redownload_policy' => 'count_each',
]));

$inactiveMeta = $validMeta;
$inactiveMeta['_sr_plan_active'] = 0;
$inactivePlan = MembershipPlanRules::fromMeta($inactiveMeta);
sr044_expect_exception('plan_not_for_sale', fn () => $inactivePlan->assertSellable());
sr044_assert(! $inactivePlan->isSellable(), 'inactive plan is not sellable');

echo "SR-044 membership plan rules checks passed.\n";

