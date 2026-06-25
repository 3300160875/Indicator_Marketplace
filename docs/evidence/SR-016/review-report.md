# SR-016 Independent Review Report

- Review agent: Hilbert (`019efe52-0398-7e30-a78c-a97aa569461d`)
- Reviewed at: 2026-06-25T10:25:56Z
- Scope: staged SR-016 implementation for resource version migration definition, statuses, workflow and repository support.

## Findings

### Important 1 — Direct current creation bypass

The first review found that `InMemoryResourceVersionRepository::create()` accepted a new `ResourceVersion` with `isCurrent=true`, allowing current state to bypass the activation lock path.

Resolution:
- Added failing evidence in `docs/evidence/SR-016/resource-versions-check.php` and module regression assertions in `packages/sr-core/tests/run.php`.
- Updated `create()` to reject direct current creation.
- Current state must now be produced through `activateCurrent()`.

### Important 2 — Activation without clean scan

The first review found that a `review` version with `scanStatus=failed|infected|pending` could be activated.

Resolution:
- Added failing evidence for a failed scan activation attempt.
- Updated `activateCurrent()` to require `ResourceVersionScanStatus::Clean`.
- The failed activation attempt still records the resource transaction lock, matching the lock-first activation semantics.

### Minor 1 — Migration DDL coverage

The first review noted that tests did not lock enough of the table shape.

Resolution:
- Expanded evidence assertions for storage fields, `sha256`, `scan_status` default, approval and timestamp fields.
- Added core test coverage for `scan_status` default.

### Minor 2 — Task document edits

The first review noted task-card edits as a process-only scope concern.

Resolution:
- No runtime code change required; task cards are required status/evidence documentation.

## Review Outcome

The blocking review findings were addressed and covered by automated evidence:
- `php docs/evidence/SR-016/resource-versions-check.php`
- `composer test` in `packages/sr-core`
- `make lint`
