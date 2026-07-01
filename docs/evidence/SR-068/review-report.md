# SR-068 Review Report

Independent QA: PASS.

## Findings

- No blocking issues remain after remediation.
- Real REST routes now respond through the local WordPress runtime:
  - `GET /index.php?rest_route=/stock-resource/v1/me/entitlements`
  - `POST /index.php?rest_route=/stock-resource/v1/download-tokens`
- Both routes fail closed for unauthenticated requests with 401 JSON, `Cache-Control: private, no-store` and `X-Request-ID`.
- `make perf-baseline` and `make perf-compare` now use real REST HTTP JSON timing samples for entitlement/download-token API p95.
- Index coverage checks now validate required index columns, not only index names.
- Cache header, JSON body and index-column adversarial checks fail as expected in QA temporary copies.

## QA Commands

- `curl -i .../index.php?rest_route=/stock-resource/v1/me/entitlements`
- `curl -i -X POST .../index.php?rest_route=/stock-resource/v1/download-tokens`
- `python3 tools/agent/validate_docs.py`
- `git diff --check`
- `docker compose ps`
- `node --check tests/performance/sr068-performance-check.mjs`
- `php -l tests/performance/sr068-api-timing.php`
- `make perf-baseline && make perf-compare`
- `make test-unit MODULE=sr-core`
- `make test-unit MODULE=sr-entitlements`
- `make test-unit MODULE=sr-private-downloads`

## Non-Blocking Follow-Ups Addressed

- `commands.log` path scope language was corrected to distinguish primary SR-068 paths from user-authorized runtime unblock paths.
- `X-Request-ID` is now checked by `make perf-compare` for protected REST traces.
- PR URL, commit SHA and final diff stat are to be filled after the GitHub PR is created.
