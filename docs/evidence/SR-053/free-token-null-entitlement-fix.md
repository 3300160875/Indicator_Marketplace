# SR-053 Follow-up Fix: Free Tokens Without Entitlement

## Issue

SR-054 review found that real FREE access decisions can have `entitlement_id = null`, while SR-053 required a positive entitlement id for every token. This made the free-resource token path fail before SR-054 could correctly create a token.

## Fix

- Changed `sr_download_tokens.entitlement_id` schema definition from `NOT NULL` to `NULL`.
- Changed `DownloadTokenIssueRequest`, `DownloadTokenIssueResult`, and `DownloadTokenRecord` to allow `?int $entitlementId`.
- Kept positive validation when an entitlement id is provided.
- Added SR-053 evidence covering free token issue with `entitlementId: null`.

## Verification

- `php docs/evidence/SR-053/download-token-check.php`
- `php -l packages/sr-private-downloads/src/Token/DownloadTokenService.php`
- `make test-concurrency TEST=DownloadTokens`
- `make test-unit MODULE=sr-private-downloads`
- `git diff --check`
- `python3 tools/agent/validate_docs.py`
