# SR-054 Completion Report

## Task / status

- Task: SR-054 实现创建下载令牌 API
- Status: implementation complete, submitted for REVIEW
- Branch: `feat/SR-054-create-token-api`

## Files changed

- `packages/sr-private-downloads/src/Rest/CreateDownloadTokenController.php`
- `docs/evidence/SR-054/create-download-token-api-check.php`
- `docs/evidence/SR-054/commands.log`

## Contract changes

- Added a registerable support controller for creating download tokens.
- Controller re-checks access through an injected access decision gateway inside the transaction.
- Added `EntitlementServiceAccessDecisionGateway` to wrap the real `EntitlementService`.
- Added `QuotaServiceReservationGateway` to wrap the real `QuotaService`.
- VIP source reserves quota before issuing token.
- Free and purchase-style sources do not reserve VIP quota.
- Idempotency is claimed inside the transaction. Same key/body returns the same response after completion, different body returns `409 idempotency_conflict`, and in-progress claims return `409 idempotency_in_progress`.
- Response returns a one-time `download_token`, TTL and expiry only; it does not expose `storage_key` or signed URL.

## Migrations

- None. SR-053 owns the `sr_download_tokens` schema.

## Commands and results

- `php docs/evidence/SR-054/create-download-token-api-check.php` -> pass
- `php -l packages/sr-private-downloads/src/Rest/CreateDownloadTokenController.php` -> pass
- `make test-unit MODULE=sr-private-downloads` -> pass
- `make test-integration TEST=Downloads` -> pass
- `make test-concurrency TEST=DownloadTokens` -> pass
- `make lint` -> pass
- `make test` -> pass
- `git diff --check` -> pass
- `python tools/agent/validate_docs.py` -> failed because this environment has no `python` executable
- `python3 tools/agent/validate_docs.py` -> pass

Full output: `docs/evidence/SR-054/commands.log`.

## Security / permission / concurrency checks

- Access is decided inside the transaction before quota reservation and token issue.
- VIP flow evidence order: `decide -> reserve -> issue`.
- Real `EntitlementService` FREE decision with `entitlement_id = null` creates a token successfully after the SR-053 nullable entitlement follow-up.
- Real `QuotaService` adapter reserves quota and binds its reservation id to the token.
- Quota reserve failure returns a stable error and does not issue a token.
- Idempotent replay does not reserve quota twice.
- In-progress idempotency claim does not reserve quota or issue a token.
- Idempotency conflict is stable and does not issue a new token.
- Failed terminal outcomes are completed in the idempotency store, so denied/quota failure replays return the same business error instead of staying in progress.
- Denied access fails closed with 403.
- Response explicitly excludes `storage_key` and `signed_url`.

## Known limitations

- This task provides controller support only. WordPress route registration is deferred to a later task that allows plugin bootstrap changes.
- Real AccessDecisionService, QuotaService and database-backed idempotency adapters will be wired by later integration tasks.

## Rollback

- Remove `packages/sr-private-downloads/src/Rest/CreateDownloadTokenController.php`.
- Remove SR-054 evidence files and revert SR-054 status entries.
- No data rollback is required.

## Next safe task(s)

1. SR-055: 私有对象 302 交付链路。
2. SR-056: 下载事件审计与配额结算。

## Commit / PR

- Commit: pending
- PR: pending
