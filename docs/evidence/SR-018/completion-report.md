# SR-018 Completion Report

- Task / status: SR-018, implementation ready for review.
- Branch: `feat/SR-018-public-rest-api`.
- Scope completed: added public REST route catalog, canonical resource query, list/detail presenter, stable public errors and taxonomy vocabulary presenter.
- Files changed: `packages/sr-core/src/Rest/Public/**`, `packages/sr-core/tests/run.php`, `docs/contracts/openapi.yaml`, `docs/evidence/SR-018/**`, status/task documentation.
- Contract changes: OpenAPI now documents `canonical_query`, `ResourceView`, `VersionView`, `PublicTaxonomyVocabulary`, `sr_invalid_filter` and `sr_resource_unavailable`.
- Migrations: none.
- Commands and results: see `docs/evidence/SR-018/commands.log`.
- Security/permission/concurrency checks: routes are read-only public GET definitions; unknown or malformed filters raise `sr_invalid_filter`; missing/unpublished detail returns `sr_resource_unavailable`; resource payloads continue to flow through SR-017 public DTOs that hide storage keys, hashes and private meta.
- Known limitations: WordPress REST registration is deferred because startup/runtime files are outside SR-018 allowed paths.
- Rollback: revert the SR-018 commit/PR; no database state is changed.
- Next safe task(s): SR-022 实现首页、导航、页脚与专题区；SR-023 实现分类、筛选、搜索与分页页面。
- Commit/PR: pending.
