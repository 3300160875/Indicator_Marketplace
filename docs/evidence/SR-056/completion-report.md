# SR-056 Completion Report

## Task / status

- Task: SR-056 实现下载事件结算与失败补偿
- Status: implementation complete, submitted for REVIEW
- Branch: `feat/SR-056-download-settlement`

## Files changed

- `packages/sr-private-downloads/src/Application/DownloadSettlementService.php`
- `docs/evidence/SR-056/download-settlement-check.php`
- `docs/evidence/SR-056/commands.log`

## Contract changes

- Added `DownloadSettlementService` support layer for redirected, failed, and expired-token settlement.
- Added `DownloadSettlementRepository`, `SettlementQuotaGateway`, `SettlementNotifier`, and `SettlementClock` contracts.
- Added `DownloadReconcileRequest`, `DownloadSettlementResult`, and `DownloadReconcileCommand` for the `sr downloads:reconcile --dry-run` command handling boundary.
- Added `QuotaServiceSettlementGateway` to adapt SR-050 `QuotaService` commit/release results.
- Reconciliation is idempotent by `request_id` and `token_id` download event presence and does not double commit or release quota.
- Repository implementations must run settlement under a token-row lock and guard `request_id`/`token_id` event uniqueness for the callback duration.

## Migrations

- None. The service targets the existing `sr_download_events` contract from SR-043.

## Commands and results

- `php -l packages/sr-private-downloads/src/Application/DownloadSettlementService.php` -> pass
- `php -l docs/evidence/SR-056/download-settlement-check.php` -> pass
- `php docs/evidence/SR-056/download-settlement-check.php` -> pass
- `make test-unit MODULE=sr-private-downloads` -> pass
- `make test-integration TEST=Downloads` -> pass
- `make test-concurrency TEST=DownloadTokens` -> pass
- `git diff --check` -> pass
- `python tools/agent/validate_docs.py` -> failed because this environment has no `python` executable
- `python3 tools/agent/validate_docs.py` -> pass
- `make lint` -> pass
- `make test` -> pass

Full output summary: `docs/evidence/SR-056/commands.log`.

## Security / permission / concurrency checks

- Settlement uses only IDs, status snapshots, request IDs, and failure codes; no raw token or storage key is written to event records.
- Redirected settlement commits quota once and writes a counted `redirected` event.
- Failed settlement releases quota once and writes an uncounted `failed` event with sanitized `error_code`.
- Expired issued-token reconciliation marks token expired, releases quota, and writes a stable `token_expired` event.
- Expired-token quota release failure leaves the token retryable and does not write an event, so a later reconcile can release safely.
- Dry-run reconciliation reports intended repair without quota mutation or event writes.
- Missing token / failed quota settlement emits an alert through `SettlementNotifier`.
- Replays by request_id and duplicate token_id return `already_settled` and do not double commit/release quota.
- SR-050 `QuotaService` integration evidence verifies success changes `used_count +1 / reserved_count -1` and failure changes `reserved_count -1` without incrementing `used_count`.

## Known limitations

- The task allowed code path only permits `DownloadSettlementService.php`; therefore this task provides the command handler boundary for `sr downloads:reconcile`, not real WP-CLI registration in a plugin bootstrap file.
- Persistent repository implementation remains integration work for a later allowed-path task.

## Rollback

- Remove `packages/sr-private-downloads/src/Application/DownloadSettlementService.php`.
- Remove SR-056 evidence files and revert SR-056 status entries.

## Next safe task(s)

1. Independent QA/review to move SR-056 from REVIEW to VERIFIED.
2. SR-057 private download security hardening, if dependencies are satisfied.

## Commit / PR

- Commit: `388246d`
- PR #66: https://github.com/3300160875/Indicator_Marketplace/pull/66
