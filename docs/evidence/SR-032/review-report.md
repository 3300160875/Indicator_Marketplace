# SR-032 Review Report

- Review scope: `packages/sr-core/src/Commerce/**` and `docs/evidence/SR-032/commerce-access-check.php`.
- Result: pass for VERIFIED handoff after independent QA recheck.
- Independent QA: Epicurus reviewed PR #33, initially found that `ProductType::MembershipPlan` with `membership_plan` meta could pass the resource purchase validator; fix `432fb99` now rejects non-resource product types before meta validation.
- Recheck: Epicurus confirmed PASS with no remaining blockers after running PR checks, `commerce-access-check.php`, package tests, repository lint/test, docs validation and diff checks.
- Access modes: `free`, `purchase`, `vip`, `purchase_or_vip` and `unavailable` are controlled by `AccessMode`.
- Server recalculation: payable resources use `PriceBook` and `DiscountPolicy`; client-submitted amounts are ignored.
- Product boundary: `ResourcePurchaseValidator` accepts only `ProductType::Resource`; membership plan requests are rejected even when request type and meta both declare `membership_plan`.
- Snapshot: order item snapshot includes immutable product type, resource ID, version ID, price ID, amounts, access mode and rules version.
- Residual risk: live EDD price API wiring is deferred; this task provides the commerce validation contract and deterministic tests.
