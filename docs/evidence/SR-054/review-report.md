# SR-054 Independent Review Report

## Reviewer

- Reviewer: Curie subagent
- Mode: read-only QA
- Result: PASS after fixes

## Initial findings

The first review found four blocking issues:

1. The controller used custom gateways without a concrete adapter to the real `EntitlementService` / `QuotaService`.
2. FREE access decisions from the real `EntitlementService` can have `entitlement_id = null`, which SR-053 originally rejected.
3. Quota reserve failure had no stable result contract and could only fail through exceptions.
4. Idempotency lookup happened outside the transaction and was not an atomic claim.

## Fixes applied

- Merged SR-053 follow-up PR #61 so free download tokens can store `entitlement_id = null`.
- Added `EntitlementServiceAccessDecisionGateway` that wraps the real `EntitlementService`.
- Added `QuotaServiceReservationGateway` that wraps the real `QuotaService` and maps failed reserve results to stable error codes.
- Changed idempotency store contract from `find/store` to transaction-scoped `claim/complete`.
- Added explicit `idempotency_in_progress` handling.
- Completed terminal failure responses in the idempotency store so denied/quota failure replay returns the same business error.
- Extended SR-054 evidence for real FREE/null entitlement, real QuotaService reservation, quota failure, and in-progress idempotency.
- Extended SR-054 evidence for denied/quota failure idempotent replay.

## Verification after fixes

- `php docs/evidence/SR-054/create-download-token-api-check.php` -> pass
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

- Denied and quota reserve failure branches now complete the idempotency record.
- Replay uses the stored `statusCode` and response body.
- Real `EntitlementService` and `QuotaService` wrappers are present.
- No `storage_key`, `signed_url` or `download_url` field is returned.
- Replaying quota failure returns `409 quota_exhausted`.
- Replaying denied access returns `403 no_entitlement`.
