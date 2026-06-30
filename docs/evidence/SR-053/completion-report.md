# SR-053 Completion Report

## Task / status

- Task: SR-053 创建 `sr_download_tokens` 表与令牌服务
- Status: implementation complete, submitted for REVIEW
- Branch: `feat/SR-053-download-tokens`

## Files changed

- `packages/sr-private-downloads/src/Token/DownloadTokenService.php`
- `docs/evidence/SR-053/download-token-check.php`
- `docs/evidence/SR-053/commands.log`

## Contract changes

- Added `DownloadTokenSchema` defining `{prefix}sr_download_tokens`.
- Added `DownloadTokenService` to issue and consume download tokens.
- Raw token is generated from 32 bytes of CSPRNG entropy and encoded as Base64URL without padding.
- Persistent storage contract stores only `token_hash`, never raw token.
- Token records bind user, resource, version, entitlement and quota reservation.
- Default TTL is 120 seconds.
- Tokens are single-use: first consume marks `consumed`; subsequent consume returns `token_already_used`.
- Consume is represented as an atomic repository contract: `consumeIssuedToken()` only succeeds when the token is still `issued`, unused, unexpired and bound to the requested user/resource/version.
- `request_id` and `token_hash` uniqueness are enforced by schema and repository.

## Migrations

- Schema definition only in this task; no live database was mutated.
- Table: `sr_download_tokens`
- Unique keys:
  - `uq_download_token_request (request_id)`
  - `uq_download_token_hash (token_hash)`

## Commands and results

- `php docs/evidence/SR-053/download-token-check.php` -> pass
- `php -l packages/sr-private-downloads/src/Token/DownloadTokenService.php` -> pass
- `make test-unit MODULE=sr-private-downloads` -> pass
- `make test-integration TEST=Downloads` -> pass
- `make test-concurrency TEST=DownloadTokens` -> pass
- `make lint` -> pass
- `make test` -> pass
- `git diff --check` -> pass
- `python tools/agent/validate_docs.py` -> failed because this environment has no `python` executable
- `python3 tools/agent/validate_docs.py` -> pass

Full output: `docs/evidence/SR-053/commands.log`.

## Security / permission / concurrency checks

- Raw token is returned only in `DownloadTokenIssueResult::rawToken`.
- `DownloadTokenRecord::toStorageArray()` contains only `token_hash`.
- `safeContext()` for issue and consume results excludes raw token.
- Duplicate `request_id` and duplicate `token_hash` fail with stable error codes and do not leak raw token in exception messages.
- Consume validates user/resource/version binding before marking consumed.
- Concurrency/replay evidence exercises the atomic consume repository contract; the second consume attempt fails with `token_already_used`.
- Expired tokens and reused tokens fail closed.
- Deterministic fixed-byte generator is used only for evidence; production default uses `random_bytes(32)`.

## Known limitations

- This task provides schema and service support only. Runtime migration registration, REST route wiring, quota reservation commit/release wiring and object storage redirect are handled by later SR-054/SR-055/SR-056 tasks.
- `python` executable is unavailable in this environment; `python3` is the verified documentation validator.

## Rollback

- Remove `packages/sr-private-downloads/src/Token/DownloadTokenService.php`.
- Remove SR-053 evidence files and revert SR-053 status entries.
- If a future environment has already created `sr_download_tokens`, drop that table only after ensuring no active download delivery flow depends on it.

## Next safe task(s)

1. SR-054: 下载令牌 REST API / 校验链路。
2. SR-055: 私有对象 302 交付链路。

## Commit / PR

- Commit: pending
- PR: pending
