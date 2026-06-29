# SR-040 Review Report

- Review scope: `packages/sr-payment-gateways/src/Application/PaymentReviewService.php` and `docs/evidence/SR-040/payment-review-service-check.php`.
- Scope verdict: PASS.
- 审批逻辑:
  - 首次审批成功触发订单完成回调。
  - 重放已审批记录返回 `idempotent_replay=true`，并校验 `idempotency` / 审批人一致性，不再次触发订单完成回调。
  - `lock_version` 不一致、金额不匹配、指纹重复、已存在其它审核人等异常场景有明确错误码返回。
- 数据一致性:
  - `approval_fingerprint`、`lock_version`、`decision_code`、`approval_idempotency_key_hash`、`reviewer_id`、`reviewed_at` 更新完整。
  - 重复提交（同 submission 不同幂等键）返回 `idempotency_conflict`。
- 订单完成回调:
  - 回调返回异常时抛出 `complete_order_failed`。
  - 完成失败后 submission 回到 `under_review`，记录 `ORDER_COMPLETION_FAILED`，并释放交易指纹/审批幂等字段以便后续重试。
  - 失败恢复后的重试审批使用恢复锁版本成功完成订单，证明该路径不是仅状态回滚，而是可真实恢复。

## Independent QA

- `php docs/evidence/SR-040/payment-review-service-check.php` 输出：`SR-040 payment review service checks passed.`
