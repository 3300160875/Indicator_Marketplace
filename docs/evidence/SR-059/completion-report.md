# SR-059 Completion Report

## Task / status

- Task: SR-059 创建版权记录与发布 Gate
- Status: implementation complete, submitted for REVIEW
- Branch: `feat/SR-059-rights-publication-gate`

## Files changed

- `packages/sr-admin-ops/src/Rights/RightsAuditEvent.php`
- `packages/sr-admin-ops/src/Rights/RightsEvidenceAccessPolicy.php`
- `packages/sr-admin-ops/src/Rights/RightsEvidencePolicy.php`
- `packages/sr-admin-ops/src/Rights/RightsException.php`
- `packages/sr-admin-ops/src/Rights/RightsExpiryDecision.php`
- `packages/sr-admin-ops/src/Rights/RightsExpiryPolicy.php`
- `packages/sr-admin-ops/src/Rights/RightsPublicationDecision.php`
- `packages/sr-admin-ops/src/Rights/RightsPublicationGate.php`
- `packages/sr-admin-ops/src/Rights/RightsPublicationRequest.php`
- `packages/sr-admin-ops/src/Rights/RightsRecord.php`
- `docs/evidence/SR-059/rights-gate-check.php`
- `docs/evidence/SR-059/commands.log`
- `docs/evidence/SR-059/completion-report.md`
- `docs/evidence/SR-059/review-report.md`

## Contract changes

- Added an admin-ops Rights support layer for paid publication decisions.
- Added `RightsRecord` shape aligned with `wp_sr_rights_records`.
- Added `RightsEvidencePolicy` to require private evidence storage keys.
- Added `RightsEvidenceAccessPolicy` for least-privilege evidence access; it supports the contract capability `view_sr_rights_evidence`, remains compatible with the current admin-ops matrix capability `sr_review_rights_evidence`, and keeps both paths row-level owner scoped.
- Added configurable `RightsExpiryPolicy` with warning lead days and expired action.
- Added `RightsPublicationGate` and decision DTOs for publication and new-token issuance checks.
- Added audit event DTOs for blocked publication decisions without raw evidence storage keys.
- Publication requests require the bound rights record ID and resource ID to match the approved record.

## Migrations

- None. SR-059 consumes the existing `wp_sr_rights_records` contract from `docs/contracts/schema.sql`.

## Commands and results

- `php docs/evidence/SR-059/rights-gate-check.php` -> pass after RED failure before implementation
- `composer validate --strict` -> pass
- `make lint` -> pass
- `make test-unit MODULE=rights` -> pass, but currently maps to `packages/sr-entitlements`
- `make test-unit MODULE=sr-admin-ops` -> pass
- `make test-integration` -> pass
- `git diff --check` -> pass
- `python tools/agent/validate_docs.py` -> failed because this environment has no `python` executable
- `python3 tools/agent/validate_docs.py` -> pass
- `php -l docs/evidence/SR-059/rights-gate-check.php` -> pass
- `php -l` for every `packages/sr-admin-ops/src/Rights/*.php` -> pass

Full output summary: `docs/evidence/SR-059/commands.log`.

## Security / permission / concurrency checks

- Paid access modes `purchase`, `purchase_or_vip`, and `vip` require `rights_status=approved`.
- Paid access modes require a matching approved rights record.
- Evidence storage keys must be private storage keys; public URLs, absolute paths, and traversal paths are rejected.
- Evidence access accepts the contract capability `view_sr_rights_evidence`, remains compatible with `sr_review_rights_evidence`, rejects users without rights evidence capability, and denies owner mismatches.
- Expiring rights emit a configurable warning before expiry.
- Expired rights can be configured either to warn only or to pause publication and new-token issuance.
- Taken-down resources immediately block publication and new-token issuance.
- Audit payloads contain stable IDs, issue codes, and snapshots only; they do not contain evidence storage keys.

## Known limitations

- No WordPress hook, REST controller, WP-CLI command, or database repository is wired in SR-059 because the allowed production path is limited to `packages/sr-admin-ops/src/Rights/**`.
- Existing `make test-unit MODULE=rights` maps to `packages/sr-entitlements`; SR-059 records this deviation and also runs `make test-unit MODULE=sr-admin-ops`.
- `python tools/agent/validate_docs.py` cannot run in this environment because `python` is not installed; `python3` is the validated replacement.

## Rollback

- Remove `packages/sr-admin-ops/src/Rights/**`.
- Remove `docs/evidence/SR-059/`.
- Re-run `composer validate --strict`, `make lint`, `make test-unit MODULE=sr-admin-ops`, `make test-integration`, and `python3 tools/agent/validate_docs.py`.

## Next safe task(s)

1. Independent QA/review to move SR-059 from REVIEW to VERIFIED.
2. Continue W11 rights/compliance follow-up tasks after SR-059 is merged.

## Commit / PR

- Commit: `ed49b9c`
- PR #73: https://github.com/3300160875/Indicator_Marketplace/pull/73
