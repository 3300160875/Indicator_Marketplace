# SR-036 Completion Report

- Task / status: SR-036, VERIFIED.
- Branch: `feat/SR-036-manual-qr-gateway`.
- Scope completed: Manual QR gateway config/request/intent, payment submission state machine, lock-version checks, transaction fingerprint, approval guard and idempotent replay handling.
- Files changed: `packages/sr-payment-gateways/src/Gateway/ManualQr/**`, `docs/evidence/SR-036/**`, status/task documentation.
- Contract changes: `ManualQrGateway` is controlled by `manualPaymentEnabled`, returns `pending` EDD order status and manual-review copy; `PaymentApprovalService` requires verified bill fingerprint/amount and never auto-completes EDD orders.
- Migrations: none.
- Commands and results: see `docs/evidence/SR-036/commands.log`.
- Security/permission/concurrency checks: lock version mismatch fails, invalid state transitions fail, same bill fingerprint is stable, proof hash alone cannot approve, idempotent approval replay does not bump lock version, source avoids direct request/db/EDD completion calls.
- Independent QA: Dalton PASS after fix `2e82146`; idempotent approval replay now validates lock version, proof hash and bill fingerprint/amount before returning success.
- Known limitations: durable submission table, reviewer permissions, EDD order completion transaction and audit persistence are deferred to downstream payment tasks.
- Rollback: revert SR-036 commit/PR; keep `SR_MANUAL_PAYMENT_ENABLED` false in runtime config.
- Next safe task(s): SR-037 创建 sr_payment_submissions 表与仓储；SR-038 实现付款凭证提交接口。
- Commit/PR: https://github.com/3300160875/Indicator_Marketplace/pull/37.
