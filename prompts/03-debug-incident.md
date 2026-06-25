# Prompt: debug an incident

Do not edit production data first. Capture environment versions, request_id, logs and a minimal
reproduction. Create a regression test, then implement the smallest compatible fix. For data
repair provide `--dry-run`, affected-row count, idempotency key, backup and rollback. Re-run the
module suite, P0 E2E, concurrency/upgrade tests as applicable, and write a postmortem/handoff.
