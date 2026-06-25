# sr-contracts

Shared pure-PHP contracts for the Stock Resource platform.

This package contains DTOs, value objects, enums, domain exceptions and service
interfaces consumed by later modules. It must remain independent from WordPress,
EDD bootstrap and framework globals.

## Local checks

```bash
composer --working-dir=packages/sr-contracts validate --strict
php packages/sr-contracts/tests/run.php
```
