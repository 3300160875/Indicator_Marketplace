# SR-017 Review Report

- Review type: structured local review fallback
- Reason: delegated review agent `019efe5b-95ed-7b92-8b3e-b33bb039731d` failed because the account hit the usage limit.
- Reviewed at: 2026-06-26
- Scope: `ResourceService`, `ResourceView`, `VersionView`, SR-017 evidence and package tests.

## Findings

### Important 1 — Version DTO exposed file hash

The first local review found that `VersionView::toArray()` exposed `sha256`. The task only explicitly forbids `storage_key` and internal notes, but public DTOs should use a minimal disclosure model and the public page does not need file integrity hashes.

Resolution:
- Added failing evidence in `docs/evidence/SR-017/resource-service-check.php`.
- Removed `sha256` from `VersionView` public output.
- Added package regression assertion in `packages/sr-core/tests/run.php`.

### Important 2 — Unknown taxonomy passthrough

The first local review found that `ResourceService::publicTaxonomies()` passed through all taxonomy keys. A future adapter could accidentally include internal review terms.

Resolution:
- Added failing evidence for `sr_internal_review`.
- Added a public taxonomy whitelist: `download_category`, `sr_platform`, `sr_indicator_type`, `sr_strategy_tag`, `sr_content_type`.
- Added package regression assertion in `packages/sr-core/tests/run.php`.

### Scope Review

- Production changes are limited to `packages/sr-core/src/Application/ResourceService.php` and `packages/sr-core/src/Dto/**`.
- No REST controller, theme presenter, WordPress hook or real repository runtime was introduced.
- DTOs serialize through `toArray()` and use snake_case public keys.
- Public output excludes storage keys, storage provider/bucket, file hash, raw `_sr_*` meta keys, internal notes, rights record id and raw risk level.

## Review Outcome

The local review findings were addressed and covered by automated evidence:
- `php docs/evidence/SR-017/resource-service-check.php`
- `composer test` in `packages/sr-core`
- `make lint`
