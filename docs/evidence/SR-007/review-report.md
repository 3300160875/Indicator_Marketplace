# SR-007 Review Report

- Reviewer: Bernoulli subagent
- Reviewed range: `2b63534..405f304`
- Date: 2026-06-25

## Findings

Critical: none.

Important, addressed:

- `UtcDateTime` accepted impossible calendar dates because PHP normalized them. Added a failing test for `2026-02-31T06:45:00Z` and changed the value object to reject normalized dates.

Minor, addressed:

- `OrderCompletedEvent` and `OrderRefundedEvent` now validate that all item IDs are `PositiveId` instances during construction.
- Added `IdempotencyKey` value object and included it in `DownloadTokenIssuer::issue()` to reflect the required download token idempotency header.
- Expanded the package test scan for WordPress/EDD runtime patterns beyond `wp_` and `add_action`.

## Verdict

Review follow-up completed. SR-007 is ready for final verification after the updated branch passes local and CI checks.
