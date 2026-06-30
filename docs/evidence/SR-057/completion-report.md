# SR-057 Completion Report

## Task / status

- Task: SR-057 实现下载限流、防重放与异常规则
- Status: implementation complete, submitted for REVIEW
- Branch: `feat/SR-057-download-security`

## Files changed

- `packages/sr-private-downloads/src/Security/DownloadSecurityPolicy.php`
- `packages/sr-private-downloads/src/Security/DownloadSecurityRequest.php`
- `packages/sr-private-downloads/src/Security/DownloadSecurityDecision.php`
- `packages/sr-private-downloads/src/Security/RateLimitRule.php`
- `packages/sr-private-downloads/src/Security/SecurityEventRecord.php`
- `packages/sr-private-downloads/src/Security/DownloadSecurityStore.php`
- `packages/sr-private-downloads/src/Security/DownloadSecurityEventSink.php`
- `packages/sr-private-downloads/src/Security/InMemoryDownloadSecurityStore.php`
- `packages/sr-private-downloads/src/Security/RecordingDownloadSecurityEventSink.php`
- `packages/sr-private-downloads/src/Rest/ConsumeDownloadTokenController.php`
- `docs/evidence/SR-057/download-security-check.php`
- `docs/evidence/SR-057/commands.log`

## Contract changes

- Added `DownloadSecurityPolicy` support layer.
- Added `DownloadSecurityRequest`, `DownloadSecurityDecision`, and `RateLimitRule` DTOs.
- Added `DownloadSecurityStore` and `DownloadSecurityEventSink` contracts.
- Added in-memory evidence implementations for rate-limit counters, replay fingerprints, reversible account-sharing restrictions, and security event recording.
- Added `DeliverySecurityGateway`, `DeliverySecurityDecision`, and `DownloadSecurityPolicyGateway` integration to the consume-download-token support layer.
- User authorized expanding SR-057 implementation to `packages/sr-private-downloads/src/Rest/ConsumeDownloadTokenController.php` for real consume-chain enforcement.

## Migrations

- None.

## Commands and results

- `php -l packages/sr-private-downloads/src/Security/DownloadSecurityPolicy.php` -> pass
- `php -l docs/evidence/SR-057/download-security-check.php` -> pass
- `php docs/evidence/SR-057/download-security-check.php` -> pass
- `make test-security` -> failed because the repository has no `test-security` target
- `composer audit` -> pass
- `npm audit --audit-level=high` -> pass
- `git diff --check` -> pass
- `python tools/agent/validate_docs.py` -> failed because this environment has no `python` executable
- `python3 tools/agent/validate_docs.py` -> pass
- `make test-unit MODULE=sr-private-downloads` -> pass
- `make test-integration TEST=Downloads` -> pass
- `make test-concurrency TEST=DownloadTokens` -> pass
- `make lint` -> pass
- `make test` -> pass

Full output summary: `docs/evidence/SR-057/commands.log`.

## Security / permission / concurrency checks

- User, IP, and resource windows are evaluated independently.
- Token fingerprint replay blocks before counters are updated.
- Consume-download-token integration blocks replay before token lock and blocks rate limits before signing/quota mutation.
- Account-sharing risk first emits a non-blocking warning, then applies only a reversible restriction with `retry_after_utc`.
- Every blocking decision writes a `download.security.blocked` event with stable code and request_id.
- Security events persist only hashed IP/UA and hashed token fingerprint, not raw tokens or storage keys.
- Inputs validate UUID request IDs, positive IDs, SHA-256 IP/UA hashes, ISO-8601 timestamps, and fixed 64-hex HMAC/SHA-256 token fingerprints.
- Security support classes are PSR-4 compatible one-class-per-file.

## Known limitations

- Persistent store implementation remains a later integration task.
- `make test-security` is not defined in the repository yet; evidence records the deviation and replacement commands.

## Rollback

- Remove `packages/sr-private-downloads/src/Security/DownloadSecurityPolicy.php`.
- Remove SR-057 evidence files and revert SR-057 status entries.

## Next safe task(s)

1. Independent QA/review to move SR-057 from REVIEW to VERIFIED.
2. SR-058 Nginx 防直链与动态页面缓存例外.

## Commit / PR

- Commit: `05bb921`
- PR #69: https://github.com/3300160875/Indicator_Marketplace/pull/69
