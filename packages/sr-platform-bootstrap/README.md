# sr-platform-bootstrap

`sr-platform-bootstrap` is the Stock Resource platform MU plugin bootstrap package.

It owns only platform startup concerns:

- PHP, WordPress and Easy Digital Downloads dependency checks.
- Feature flag loading from the `SR_*` contract names in `docs/contracts/feature-flags.yaml`.
- A small explicit service container.
- Service provider registration.
- Safe degradation when dependencies are missing: frontend requests do not white-screen, and WordPress admin receives an explicit blocking notice.

It must not implement payment, entitlement, download delivery, EDD projection or admin workflow business rules. Those belong to later first-party packages.

## Tests

```bash
composer --working-dir=packages/sr-platform-bootstrap validate --strict
composer --working-dir=packages/sr-platform-bootstrap test
```
