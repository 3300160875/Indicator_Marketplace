# SR-055 Independent Review Report

## Reviewer

- Reviewer: James subagent
- Mode: read-only QA
- Result: PASS after fixes

## Initial findings

The first review found these blocking issues:

1. Success path used sequential `markRedirected -> quota commit -> event` calls and wrote token status `redirected`, not `consumed`.
2. Token gateway did not atomically advance token state before delivery completion.
3. Signed URL gateway did not prove object existence or cap short TTL.
4. Failure path used sequential `markFailed -> quota release -> failed event` calls.
5. Response contract lacked `X-Request-ID`, and errors returned `{status, code}` instead of `{error_code, message, request_id}`.

## Fixes applied

- Added `DeliveryTransactionRunner`.
- Success path now runs `consumeForDelivery + quota commit + redirected event` inside a transaction boundary.
- Token final success status is `consumed`.
- Failure path now runs `markFailed + quota release + failed event` inside a transaction boundary.
- Signed URL gateway receives max TTL and rejects overlong TTL.
- Success responses include `X-Request-ID`; error responses include `error_code`, `message`, and `request_id`.
- Error statuses now align with OpenAPI: replay/expired token use 410 and storage/signing failures use 503.
- Missing request id now generates a deterministic valid UUID; provided request ids must be UUIDs.
- Race replay after successful signing but before transactional consume completion now returns 410.
- `SignedUrlResult::ok()` also maps invalid TTL to 503.
- Evidence now covers consumed state, transaction events, replay, expired token, object missing, overlong signed URL TTL, quota release, failed event and no storage key leak.

## Verification after fixes

- `php docs/evidence/SR-055/consume-download-token-check.php` -> pass
- `make test-unit MODULE=sr-private-downloads` -> pass
- `make test-integration TEST=Downloads` -> pass
- `make test-concurrency TEST=DownloadTokens` -> pass
- `composer validate --strict` -> pass
- `make lint` -> pass
- `make test` -> pass
- `git diff --check` -> pass
- `python3 tools/agent/validate_docs.py` -> pass

## Final reviewer conclusion

PASS.

The reviewer confirmed:

- Race replay after successful signing now returns `410 token_already_used`.
- Signed URL TTL invalid paths return `503`.
- Evidence covers `lock ok -> sign ok -> consumeForDelivery null`.
- SR-055 required commands pass.
