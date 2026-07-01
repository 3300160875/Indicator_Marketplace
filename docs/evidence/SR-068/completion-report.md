# SR-068 Completion Report

- Task / status: SR-068 性能基线、缓存与慢查询治理 — ready for review.
- Branch: `feat/SR-068-performance-baseline`
- Files changed:
  - `tests/performance/sr068-performance-check.mjs`
  - `tests/performance/sr068-api-timing.php`
  - `infra/monitoring/sr068-performance-budget.json`
  - `infra/monitoring/reports/sr068-baseline.json`
  - `infra/monitoring/reports/sr068-compare.json`
  - `infra/monitoring/reports/sr068-slow-query-report.md`
  - `Makefile`
  - `bin/dev`
  - `web/app/mu-plugins/stock-resource-runtime-loader.php`
  - `packages/sr-core/src/Runtime/CoreRuntimeRegistrar.php`
  - `packages/sr-entitlements/src/Plugin.php`
  - `packages/sr-entitlements/sr-entitlements.php`
  - `packages/sr-private-downloads/src/Plugin.php`
  - `packages/sr-private-downloads/sr-private-downloads.php`
  - `packages/sr-private-downloads/src/Rest/CreateDownloadTokenController.php`
  - `docs/evidence/SR-068/commands.log`
  - `docs/evidence/SR-068/completion-report.md`
- Contract changes: runtime REST route registration is now wired for `/stock-resource/v1/me/entitlements` and `/stock-resource/v1/download-tokens`; unauthenticated calls fail closed as 401 JSON.
- Migration impact: none.
- Configuration / feature flags: none.
- Cache / invalidation: performance budget now asserts private API `Cache-Control: private, no-store` from real REST HTTP responses, me-entitlements cache key dimensions `user_id + rules_version`, content restriction vary dimensions and non-cacheable download token responses.
- Observability: adds machine-readable SR-068 baseline/compare JSON reports plus a slow-query and N+1 Markdown report under `infra/monitoring/reports/`.
- Tooling fix: `make perf-baseline` and `make perf-compare` are now wired through `bin/dev`, because SR-068 required these commands and the pre-existing Makefile had no such targets.

## Results

- LCP p75: 140ms, budget <= 2500ms, collected from three headless Chrome runs against the local Docker nginx runtime.
- Entitlement API p95: 37.724ms, budget <= 500ms, collected from 10 real WordPress REST HTTP JSON timing samples.
- Download token API p95: 39.883ms, budget <= 500ms, collected from 10 real WordPress REST HTTP JSON timing samples.
- Service-layer supplemental p95: 0.043ms for `MeEntitlementsController`, 0.029ms for `CreateDownloadTokenController`.
- Slow query threshold: 100ms.
- Query plans checked: 7; all required indexes and covering columns present in `docs/contracts/schema.sql`.
- Endpoint query counts checked: 5; all below budget, no N+1 risk reported.

## Security / Correctness

- No business rules changed.
- No dependency versions changed.
- REST route permissions fail closed for unauthenticated users; performance HTTP samples intentionally measure the 401 JSON permission boundary without exposing entitlement or token data.
- No real credentials, cookies, raw tokens, signed URLs, payment proofs or production personal data are stored in evidence.
- Cache checks consume runtime REST HTTP headers and service-layer cache traces to protect user-specific entitlement and token responses from shared caching.
- Slow-query checks are read-only and inspect the committed schema contract.

## Known Limitations

- This task establishes a local Docker performance baseline and budget gate. It does not run external production Lighthouse.
- Product runtime optimization was not required because the current local runtime trace, schema/index and cache budgets pass; no business code was changed.
- The local shell lacks `python`; `python3 tools/agent/validate_docs.py` is the successful equivalent validation command.

## Rollback

- Revert this task branch or remove the SR-068 Makefile/bin/dev target additions, runtime REST loader/wiring changes, `tests/performance/sr068-performance-check.mjs`, `tests/performance/sr068-api-timing.php`, `infra/monitoring/sr068-performance-budget.json`, generated SR-068 reports and SR-068 evidence/status entries.

## Next Safe Task

1. SR-069 after SR-066 is unblocked and verified, or continue with independent ready tasks that do not depend on SR-066.
2. SR-066 unblock remains separate: root Playwright dependencies, CI/e2e script and real application workflow endpoints still need their own scoped task.
