# SR-035 Completion Report

- Task / status: SR-035, REVIEW.
- Branch: `feat/SR-035-account-order-projection`.
- Scope completed: account order repository, current-user projection service, scoped cache key, readable order/download status mapping, empty/expired/revoked/quota reset states and internal note suppression.
- Files changed: `packages/sr-core/src/Account/Orders/**`, `docs/evidence/SR-035/**`, status/task documentation.
- Contract changes: `AccountOrderProjectionService` returns a current-user-only account order projection with `state`, `cache_key`, readable status labels and sanitized item summaries.
- Migrations: none.
- Commands and results: see `docs/evidence/SR-035/commands.log`.
- Security/permission/concurrency checks: user id is required, repository filters by current user, cache key includes user id and rules version, internal review notes are never projected and source avoids direct SQL/EDD runtime/request globals.
- Known limitations: runtime account page filter wiring and durable order repository are deferred to downstream integration tasks.
- Rollback: revert SR-035 commit/PR; no live order or user data is mutated.
- Next safe task(s): SR-036 生成订单状态机与边界文案；SR-037 实现订单/权益事件 Outbox。
- Commit/PR: https://github.com/3300160875/Indicator_Marketplace/pull/36.
