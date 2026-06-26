# SR-017 Completion Report

- Task / status: SR-017, implementation ready for review.
- Branch: `feat/SR-017-resource-service-dto`.
- Scope completed: added `ResourceService`, `ResourceView` and `VersionView` for public resource presentation.
- Files changed: `packages/sr-core/src/Application/ResourceService.php`, `packages/sr-core/src/Dto/**`, `packages/sr-core/tests/run.php`, `docs/evidence/SR-017/**`, status/task documentation.
- Contract changes: public resource output is serialized through DTO `toArray()` methods using snake_case keys.
- Migrations: none.
- Commands and results: see `docs/evidence/SR-017/commands.log`.
- Security/permission/concurrency checks: unpublished, unavailable, blocked or versionless resources return `null`; DTO payloads exclude `storage_key`, storage provider/bucket, file hash, raw `_sr_*` meta keys, unknown taxonomies and internal notes.
- Known limitations: WordPress repositories, REST controllers and theme presenters are deferred to downstream SR-018/SR-022/SR-024 tasks.
- Rollback: revert the SR-017 commit; no data mutation is introduced.
- Next safe task(s): SR-018 实现公开资源与词表 REST API；SR-021 实现设计令牌与基础组件。
