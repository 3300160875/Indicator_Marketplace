# SR-037 Review Report

- Review scope: `packages/sr-payment-gateways/src/Submission/**` and `docs/evidence/SR-037/payment-submissions-check.php`.
- Scope verdict: PASS.
- State/locking invariants:
  - `submitted -> under_review -> approved/need_more_info/reject/cancel` 转换与约束明确。
  - `withState()` 每次变更都使 `lock_version` 递增，`InMemoryPaymentSubmissionRepository` 校验 `expectedLockVersion` 与存量一致性，拒绝重放或错误并发版本。
- Idempotency/fingerprint:
  - `PaymentSubmission::idempotencyHash()` 与 `submission_key` 幂等创建逻辑能返回历史记录；`transaction_fingerprint` 用于防止同一账单被多次创建提交。
  - 重复提交与重复指纹场景在检查脚本中有明确异常覆盖。
- Input and safety checks:
  - 金额格式、proof 元信息、时间字符串、提交键、路径与长度约束均已落到实体不变量中。
  - 错误信息使用可读中文消息，异常码使用稳定字符串（`invalid_state`、`duplicate_submission_key`、`lock_version_mismatch`、`invalid_transition`、`duplicate_transaction_fingerprint`）。
- Data/SQL checks:
  - 迁移 SQL 包含 `sr_payment_submissions` 表结构核心字段、唯一键、索引及金额 DECIMAL 定义。
- Independent QA status:
  - 本地复核通过：`SR-037 payment submission checks passed.`
- 风险提示：
  - 本任务仅覆盖定义层与内存仓储，未接入持久化实现与权限/审计落库；请在后续 SR-038/039 继续补齐运行时接口与审计。
