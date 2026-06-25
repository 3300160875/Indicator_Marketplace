# Prompt: independent review

Review the PR for `SR-XXX`; do not broaden scope.
Check, in order: contract/acceptance, module boundaries, capability and ownership, validation and
escaping, amount integrity, idempotency, transaction boundaries, unique keys, concurrency,
migration upgrade path, log redaction, stable errors, tests, rollback and unrelated changes.
Classify findings as blocking/major/minor with file and line evidence. Payment, entitlement,
quota, download, migration and release tasks are high risk even when CI is green.
Only an independent reviewer may move REVIEW to VERIFIED.
