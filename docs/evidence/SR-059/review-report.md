# SR-059 Review Report

## Review result

- Reviewer: Hegel subagent
- Result: PASS
- Date: 2026-06-30

## Scope reviewed

- `docs/tasks/SR-059.md`
- `docs/evidence/SR-059/*`
- `packages/sr-admin-ops/src/Rights/**`
- `docs/status/task-status.yaml`
- Relevant contracts: schema, permissions, feature flags, events, state machines and data dictionary.

## Verification performed by reviewer

- `php docs/evidence/SR-059/rights-gate-check.php` -> pass
- `composer validate --strict` -> pass
- `make lint` -> pass
- `make test-unit MODULE=rights` -> pass
- `make test-unit MODULE=sr-admin-ops` -> pass
- `make test-integration` -> pass
- `python3 tools/agent/validate_docs.py` -> pass
- `git diff --check` -> pass

## Findings and remediation

- Critical: none.
- Important: initial review found rights evidence capability naming drift between `permissions.yaml` (`view_sr_rights_evidence`) and the current admin-ops matrix (`sr_review_rights_evidence`). Fixed by supporting both capability names in `RightsEvidenceAccessPolicy`.
- Important: follow-up review found the contract capability path bypassed row-level owner scope. Fixed by requiring owner match for `view_sr_rights_evidence`, except administrators.
- Minor: `make test-unit MODULE=rights` currently maps to `packages/sr-entitlements`, and `make test-unit MODULE=sr-admin-ops` is still a skeleton runner. This is recorded in `commands.log`; SR-059 behavior is covered by `rights-gate-check.php`.

## Final reviewer assessment

- No remaining Critical or Important issues.
- Paid resources require approved rights status and a matching approved rights record.
- Evidence storage keys reject public URLs, absolute paths and traversal paths.
- Evidence access supports both contract/current capability names and remains row-level owner scoped.
- Expiry policy supports both warning-only and pause-publication modes.
- Taken-down resources block publication and new-token issuance.
- Audit payloads do not expose `evidence_storage_key`.

## Recommendation

- Proceed to PR.
