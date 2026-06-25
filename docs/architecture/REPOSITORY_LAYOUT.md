# Repository Layout

The real project repository lives at:

```text
/home/devops-ubuntu/Developer/Indicator_Marketplace/project
```

The parent workspace keeps source documents separate from project code:

```text
/home/devops-ubuntu/Developer/Indicator_Marketplace/
  project/                 # Git repository and all runtime code
  docs/
    agent-guide/           # AI Agent execution guide and workflow assets
    product-spec/          # Product, technical and page implementation documents
    archive/               # Original source package archive
```

## Project Repository

```text
project/
  .github/                 # CI, PR templates and repository automation
  config/                  # Bedrock/WordPress environment config
  infra/                   # Local Docker and smoke-test scripts
  packages/                # First-party platform packages
  web/                     # Bedrock web root and Composer-installed WordPress tree
  docs/                    # Project-governance docs, ADRs, task cards and evidence
  tools/agent/             # Task/status validation tooling
```

## First-party Packages

```text
packages/
  sr-contracts/            # Shared DTOs, value objects, enums and interfaces
  sr-platform-bootstrap/   # MU-plugin bootstrap, service container and feature flags
  sr-core/                 # Core resource and EDD integration code
  sr-entitlements/         # Entitlement and quota decisions
  sr-private-downloads/    # Private object storage and download delivery
  sr-payment-gateways/     # Manual payment proof and review flow
  sr-admin-ops/            # Admin operations, audit and support workflows
```

## Rules

- Write application code under `project/packages/**`.
- Do not edit WordPress Core, EDD Core, `vendor/` or generated dependency trees.
- Keep source/reference documents under the parent `docs/**` workspace unless they are project-governance artifacts required by CI or task review.
- Keep task evidence under `project/docs/evidence/**` so repository history can prove what was done.
