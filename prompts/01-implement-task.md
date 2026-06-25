# Prompt: implement one task

Implement task `SR-XXX` only.

1. Read `AGENTS.md`, status files, the task card, dependency evidence and referenced contracts.
2. Run `taskctl.py claim` with a branch and verify path locks.
3. Add a failing test/reproduction before production code where feasible.
4. Modify only allowed paths; do not upgrade dependencies or alter business rules.
5. Run every required command and affected regression suite.
6. Save non-sensitive evidence under `docs/evidence/SR-XXX/`.
7. Update contracts/migrations/runbooks and the task Completion Report.
8. Move to REVIEW, not DONE. Stop as BLOCKED if a contract or version assumption differs.
