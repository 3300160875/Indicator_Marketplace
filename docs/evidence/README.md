
# Task evidence

Each task writes non-sensitive, reproducible evidence under `docs/evidence/SR-XXX/`.
Cross-task runtime wiring patches may use a named evidence directory such as
`docs/evidence/runtime-wiring/` when they intentionally do not create a new SR.
Use relative paths in `task-status.yaml`. Do not commit payment proofs, raw tokens,
cookies, secrets, production database dumps or personal information.
