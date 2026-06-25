# Package dependency rules

Allowed compile/runtime dependency direction:

```text
sr-contracts
   ↑
sr-platform-bootstrap
   ↑
┌─────────┬─────────────────────┬──────────────────────┐
│ sr-core │ sr-payment-gateways │ sr-admin-ops         │
└────┬────┴──────────┬──────────┴──────────┬───────────┘
     │               │                     │
     └──────────→ sr-entitlements ←────────┘
                         │
                         └────────→ sr-private-downloads

stock-resource-theme → service interfaces / presenters only
```

- `sr-contracts` has no WordPress or EDD dependency.
- `sr-platform-bootstrap` owns the service registry, feature flags and cross-cutting adapters, not business rules.
- `sr-payment-gateways` completes an EDD order but never writes entitlement rows directly.
- `sr-entitlements` listens to completed/refunded order facts and owns all access/quota decisions.
- `sr-private-downloads` calls `EntitlementService` and `QuotaService`; it never reimplements membership rules.
- `sr-admin-ops` invokes domain services for mutations; reports may use dedicated read projections.
- The theme may not query custom tables, call `$wpdb`, complete orders or modify entitlements.
- Cyclic Composer dependencies are a blocking architecture defect.
