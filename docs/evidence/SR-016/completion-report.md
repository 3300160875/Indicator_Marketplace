# SR-016 Completion Report

- Task / status: SR-016, implementation ready for review.
- Branch: `feat/SR-016-resource-versions`.
- Scope completed: added resource version table migration definition, version and scan status enums, retryable workflow stages, repository contract and in-memory repository with transactional activation semantics.
- Files changed: `packages/sr-core/src/Version/**`, `packages/sr-core/tests/run.php`, `docs/evidence/SR-016/**`, status/task documentation.
- Contract changes: defines the `sr_resource_versions` table shape, stable version statuses, stable scan statuses, explicit upload/scan/review/activate stages and repository methods for immutable create, lookup and current activation.
- Migrations: `ResourceVersionSchemaMigration::create()` returns SQL for `{prefix}sr_resource_versions`; not wired into runtime migration registration in this task.
- Commands and results: see `docs/evidence/SR-016/commands.log`.
- Security/permission/concurrency checks: versions are immutable by id; new versions cannot set current directly; activation requires a clean scan, clears the previous current version and records a per-resource transaction lock.
- Known limitations: no real database adapter or WordPress hook wiring is added because SR-016 production paths are limited to `packages/sr-core/src/Version/**`.
- Rollback: revert the SR-016 commit; no live database mutation is introduced by this support layer.
- Next safe task(s): SR-017 实现 ResourceService 与公开 DTO；SR-021 实现设计令牌与基础组件。
