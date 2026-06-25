# SR-006 Spike Results

Status: BLOCKED for EDD runtime validation; MariaDB and MinIO checks completed.

## Environment

- WordPress: Composer locked `roots/wordpress` 7.0.
- EDD: Composer locked `wp-plugin/easy-digital-downloads` 3.6.9.
- Runtime: Docker Compose local stack from SR-003/SR-004.
- Constraint: SR-006 allowed committed paths are only `docs/adr/**` and `docs/spikes/**`.

## EDD Findings

Static inspection only:

- EDD 3.6.9 is installed by Composer under `web/app/plugins/easy-digital-downloads`.
- `edd_complete_purchase` is present in `includes/payments/actions.php`.
- EDD registers `edd_complete_purchase` on `edd_update_payment_status`.
- Refund-related hooks/functions exist, including `edd_refund_order` and deprecated compatibility hooks.

Runtime validation is blocked:

- WP-CLI is not available in host, vendor bin, or PHP container.
- The local MariaDB database has no WordPress tables after SR-003/SR-004 bootstrap.
- EDD is not activated in a disposable WordPress install.
- A hook observer/spike runner would need files outside the SR-006 allowed paths.

Conclusion: ADR-002 and ADR-003 remain proposed-blocked. Do not implement EDD adapter or entitlement assumptions until runtime proof exists.

## MariaDB Findings

Verified with disposable probe tables:

- `SELECT ... FOR UPDATE` on one connection blocked a second connection.
- Second connection returned `ERROR 1205 (HY000): Lock wait timeout exceeded`.
- Unique token duplicate insert returned `ERROR 1062 (23000)`.
- Conflicting two-row updates returned `ERROR 1213 (40001): Deadlock found when trying to get lock` for one transaction.

Conclusion: MariaDB can be authoritative for quota reservation/settlement if services use transactions, unique idempotency keys and bounded retry for 1205/1213.

## MinIO Findings

Verified with disposable bucket/object:

- Direct unauthenticated GET against a private object returned 403.
- Presigned GET generated with `mc share download --expire 120s` returned object content inside the Compose network.
- Range request against the presigned URL returned HTTP 206 Partial Content.
- A 1-second presigned URL returned 403 after expiry.

Conclusion: private object storage plus short-lived signed URLs is viable. Signed URL method/host must match the request; do not rewrite host after signing.

## Required Follow-Up

1. Add an approved disposable WP/EDD runtime spike mechanism, such as WP-CLI in dev tooling or a temporary mounted PHP runner.
2. Install WordPress in local MariaDB, activate EDD, create throwaway order/refund data and observe hooks.
3. Re-open ADR-002/ADR-003 only after runtime EDD proof exists.
