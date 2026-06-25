# SR-006 Spike Results

Status: EDD runtime validation, MariaDB and MinIO checks completed.

## Environment

- WordPress: Composer locked `roots/wordpress` 7.0.
- EDD: Composer locked `wp-plugin/easy-digital-downloads` 3.6.9.
- Runtime: Docker Compose local stack from SR-003/SR-004.
- Runtime runner: `docs/spikes/SR-006/runtime-edd-spike.php`.

## EDD Findings

- EDD 3.6.9 is installed by Composer under `web/app/plugins/easy-digital-downloads`.
- Runtime spike installed disposable WordPress, activated EDD and installed EDD component tables.
- Public EDD APIs created products, customers, orders, order items, full refunds and item-level partial refunds.
- First order completion returned true; duplicate completion returned false.
- Observed completion hooks: `edd_update_payment_status`, `edd_pre_complete_purchase`, `edd_complete_download_purchase`, `edd_complete_purchase`, `edd_after_payment_actions` and `edd_after_order_actions`.
- Observed refund hook: `edd_refund_order` with full refund args `[1, 2, true]` and partial refund args `[3, 4, false]`.
- Full refund produced sale status `refunded`, refund order ID 2 and refund item total `-12.340000000`.
- Partial item refund produced sale status `partially_refunded`, refund order ID 4 and refund item total `-3.000000000`.
- Refundability override was forced in the CLI spike to isolate refund data shape and hook payloads. Production refund eligibility remains a later policy/permission concern.

Conclusion: ADR-002 and ADR-003 are accepted. Future EDD adapter work may proceed against public APIs/hook payloads and must normalize EDD state at a project-owned boundary.

## MariaDB Findings

Verified with disposable probe tables:

- `SELECT ... FOR UPDATE` on one connection blocked a second connection.
- Second connection returned `ERROR 1205 (HY000): Lock wait timeout exceeded`.
- Unique token duplicate insert returned `ERROR 1062 (23000)`.
- Conflicting two-row updates returned `ERROR 1213 (40001): Deadlock found when trying to get lock` for one transaction.

Conclusion: MariaDB can be authoritative for quota reservation/settlement if services use transactions, unique idempotency keys and bounded retry for 1205/1213.

Reproducibility note: SR-006 preserved these as historical command summaries. Later quota implementation should promote the probes to reusable scripts or automated tests before runtime quota code ships.

## MinIO Findings

Verified with disposable bucket/object:

- Direct unauthenticated GET against a private object returned 403.
- Presigned GET generated with `mc share download --expire 120s` returned object content inside the Compose network.
- Range request against the presigned URL returned HTTP 206 Partial Content.
- A 1-second presigned URL returned 403 after expiry.

Conclusion: private object storage plus short-lived signed URLs is viable. Signed URL method/host must match the request; do not rewrite host after signing.

Reproducibility note: SR-006 preserved these as historical command summaries. Later download service work should promote the probes to reusable `mc`/`curl` scripts or automated tests.

## Required Follow-Up

1. Implement `EddOrderAdapter` in its own task using public EDD APIs and hook payloads only.
2. Add automated tests around duplicate complete, full refund and partial item refund behavior when the adapter task starts.
3. Keep EDD email side effects out of adapter tests; the SR-006 CLI spike removed email callbacks only to isolate order/refund semantics.
