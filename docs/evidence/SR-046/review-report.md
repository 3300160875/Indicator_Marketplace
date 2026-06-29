# SR-046 Review Report

- Review scope: `packages/sr-contracts/src/Entitlement/**`, `packages/sr-entitlements/src/Application/EntitlementService.php`, and `docs/evidence/SR-046/access-decision-check.php`.
- Scope verdict: PASS.

## Review focus

- AccessDecision 不返回裸 bool，包含 reason/source/entitlement/quota/expires/rules_version。
- EntitlementService 判断顺序固定为：资源状态、免费、登录、单购、人工、VIP、范围/排除、配额。
- 资源下架或 unavailable 在 free 之前拒绝，避免免费资源绕过资源状态。
- 单购优先于 VIP，人工授权优先于 VIP。
- VIP 支持 all/resources/taxonomies 范围和 excluded_resource_ids 排除。
- 多个权益并存时排序稳定，覆盖优先级、到期时间与 ID。
- 到期边界为 `now >= expires_at` 不可访问。
- 配额耗尽返回 `quota_exhausted` 并暴露 reset 信息。

## Independent QA

- `php docs/evidence/SR-046/access-decision-check.php`
- 输出：`SR-046 access decision checks passed.`

## Follow-up points

- SR-050 需要接入真实 QuotaService，替换当前服务可选 quota resolver/快照判断。
- SR-051/SR-054 应直接消费 AccessDecision，避免重新实现访问判断。
