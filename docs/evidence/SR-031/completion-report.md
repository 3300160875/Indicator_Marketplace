# SR-031 Completion Report

- Task / status: SR-031, REVIEW.
- Branch: `feat/SR-031-edd-order-adapter`.
- Scope completed: EDD 3.6.9 compatibility fixture, order/customer/item snapshots, EddOrderAdapter projection for completed and refunded events, duplicate-complete guard and EDD boundary source scan.
- Files changed: `packages/sr-core/src/Integration/Edd/**`, `docs/evidence/SR-031/**`, status/task documentation.
- Contract changes: EDD raw order/customer/item shapes are normalized into adapter DTOs and SR-007 `OrderCompletedEvent` / `OrderRefundedEvent` contracts.
- Migrations: none.
- Commands and results: see `docs/evidence/SR-031/commands.log`.
- Security/permission/concurrency checks: adapter ignores duplicate complete transitions, normalizes negative refund totals before creating Money values, and confirms EDD API touchpoints are isolated to `Integration/Edd`.
- Known limitations: repository-level `make test-unit` and `make test-integration` targets do not exist yet; SR-031 records direct replacement commands. Runtime WordPress/EDD API wiring remains deferred.
- Rollback: revert SR-031 commit/PR; no live EDD order data is mutated.
- Next safe task(s): SR-032 实现资源访问模式与价格校验；SR-033 定制 EDD 结算与数字内容条款。
- Commit/PR: pending.
