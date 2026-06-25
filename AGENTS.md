# AGENTS.md — Stock Indicator Resource Platform

This repository is executed through atomic SR tasks. Product behavior is not inferred from code or chat history.

## Mandatory read order
1. `docs/status/project-status.yaml` and `docs/status/task-status.yaml`
2. Current `docs/tasks/SR-XXX.md` and its definition in `docs/tasks/backlog.yaml`
3. `docs/EXECUTION_PLAN.md`
4. Referenced ADR, OpenAPI, schema, data dictionary and tests
5. Last 20 commits touching the allowed paths

## Source-of-truth precedence
Approved Gate 0 / accepted ADR > execution plan > PRD V1.1 > OpenAPI/schema/tests > task card > current code. Stop when these disagree; do not silently pick the implementation.

## Hard rules
- Work on exactly one task ID at a time; WIP=1.
- Claim the task and allowed paths with `taskctl.py` before editing.
- Modify only declared paths. Shared contracts, migrations and root dependency files require explicit ownership.
- Never edit WordPress Core, EDD Core, `vendor/`, generated artifacts or production data.
- Do not add paid plugins, page builders, personal-code monitoring, notification/cookie readers or “免签” automation.
- Theme code never queries `sr_*` tables or mutates orders/entitlements.
- All access decisions go through `EntitlementService`; all quota changes go through `QuotaService`.
- EDD is accessed through `EddOrderAdapter`; storage through `StorageService`.
- Database changes require forward migration, checksum, fresh install, N-1 upgrade and interruption-retry tests.
- Never weaken permissions, idempotency, audit, upload validation, concurrency checks or tests to make CI pass.
- Do not update dependency versions unless the task explicitly authorizes it.
- Raw tokens, payment proofs, cookies, keys and production personal data never enter Git or evidence.

## Workflow
`BACKLOG -> READY -> IN_PROGRESS -> REVIEW -> VERIFIED -> DONE`; use `BLOCKED` with evidence when a contract, path, compliance decision or destructive migration is unclear.

```bash
python tools/agent/validate_docs.py
python tools/agent/taskctl.py ready
python tools/agent/taskctl.py claim SR-XXX --agent <name> --branch feat/SR-XXX-short-name
# implement + test + evidence
python tools/agent/taskctl.py set-status SR-XXX REVIEW --evidence docs/evidence/SR-XXX/commands.log
python tools/agent/progress_report.py
```

## Mandatory completion report
Task ID/status; files; contract/schema changes; commands/results; security/permissions/idempotency/concurrency; migration; rollback; limitations; next safe tasks; commit/PR.

## Stop conditions
Stop and mark BLOCKED when: business/payment/entitlement rules would change; a destructive migration is required; EDD/WP behavior differs from accepted ADR; an unapproved dependency is needed; another Agent owns the path; tests cannot reproduce the issue; or a copyright, securities, payment or privacy risk appears.
