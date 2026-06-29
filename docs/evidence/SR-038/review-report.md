# SR-038 Review Report

- Review scope: `packages/sr-payment-gateways/src/Rest/PaymentProofController.php` and `docs/evidence/SR-038/payment-proof-check.php`.
- Scope verdict: PASS.
- Input validation:
  - 拒绝空的 idempotency key。
  - 拒绝订单非提交用户与订单状态不允许提交。
  - 校验 `channel`、`reported_amount`（金额格式）、`reported_paid_at`（时间格式）、`proof` 输入（缺失/无效类型/重复 payload）。
  - `proof` 超过 8MiB 时拒绝。
- 提交流程与幂等性:
  - 首次提交返回 `submitted` 状态且 `lock_version = 0`。
  - 同一 `idempotency key`、同一 payload 返回同一 `submission`。
  - 同一 key 但 payload 不一致返回 `state_conflict`。
- 查询接口:
  - `getPaymentStatus` 与 `proofTimeline` 在有数据时返回可复核记录。
  - base64 data URL 场景可正确提取 MIME 并完成提交。
- 风险提示:
  - 本任务目前仍为支付凭证提交支持层，尚未接入 `sr-core` 的 REST 注册与鉴权中间件链。

## Independent QA

- `php docs/evidence/SR-038/payment-proof-check.php` 输出：`SR-038 payment proof checks passed.`
