# SR-006 EDD Runtime Spike Summary

- Generated: 2026-06-25T14:34:30+08:00
- Command: `make reset || true && make bootstrap && docker compose exec -T php php docs/spikes/SR-006/runtime-edd-spike.php > /tmp/sr006_runtime.json`
- Exit: 0
- Runner: `docs/spikes/SR-006/runtime-edd-spike.php`
- Environment: WordPress 7.0, PHP 8.3 container, EDD 3.6.9, MariaDB 10.11 Docker stack.

## Setup

- Disposable WordPress install completed.
- EDD activated.
- EDD component tables installed and checked, including `wp_edd_customers`, `wp_edd_orders`, `wp_edd_order_items`, `wp_edd_logs` and `wp_edd_sessions`.
- EDD email side-effect callbacks were removed inside the CLI spike only: `order_emails=1`, `refund_emails=1`. Hook observation remained enabled at priority 1.

## Order Completion

- First status transition from `pending` to `complete` returned `true`.
- Duplicate transition to `complete` returned `false`.
- Observed synchronous hooks for completed orders:
  - `edd_update_payment_status`
  - `edd_pre_complete_purchase`
  - `edd_complete_download_purchase`
  - `edd_complete_purchase`
  - `edd_after_payment_actions`
  - `edd_after_order_actions`

## Refunds

- Full refund:
  - Sale order ID: 1
  - Refund order ID: 2
  - Refund item parent: 1
  - Refund item total: `-12.340000000`
  - Final sale order status: `refunded`
  - `edd_refund_order` args: `[1, 2, true]`
- Partial item refund:
  - Sale order ID: 3
  - Refund order ID: 4
  - Refund item parent: 3
  - Refund item total: `-3.000000000`
  - Final sale order status: `partially_refunded`
  - `edd_refund_order` args: `[3, 4, false]`

## Database Confirmation

```text
id  status              type    parent  total
1   refunded            sale    0       12.340000000
2   complete            refund  1       -12.340000000
3   partially_refunded  sale    0       8.000000000
4   complete            refund  3       -3.000000000

order_id  product_id  quantity  total
1         10          1         12.340000000
2         10          -1        -12.340000000
3         11          1         8.000000000
4         11          -1        -3.000000000
```

## Conclusion

EDD 3.6.9 provides sufficient runtime hook and refund data for a future adapter to normalize order completion, duplicate completion, full refunds and item-level partial refunds without querying private internals directly.
