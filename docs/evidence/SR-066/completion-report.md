# SR-066 Completion Report

## Task / Status

- Task: SR-066 实现 Playwright P0 E2E 套件
- Status: REVIEW after unblock retry
- Branch: `feat/SR-066-e2e-unblock`

## Files Changed

- Root E2E tooling: `package.json`, `package-lock.json`, `bin/dev`, `.github/workflows/ci.yml`, `.gitignore`.
- Runtime wiring: `docker-compose.yml`, `web/app/mu-plugins/stock-resource-runtime-loader.php`.
- E2E suite: `tests/e2e/playwright.config.mjs`, `tests/e2e/sr066-p0.spec.mjs`, `tests/e2e/bootstrap-runtime.php`, `tests/e2e/wp/e2e-runtime.php`.
- Evidence: `docs/evidence/SR-066/commands.log`, `completion-report.md`, `review-report.md`.

## Contract Changes

- Adds a local-only E2E runtime surface under `stock-resource-e2e/v1`.
- The runtime is loaded only when `SR_E2E_ENABLED=1` and WordPress reports `local` or `development`.
- E2E endpoints require `x-sr-e2e-key`, defaulting to `local-e2e-only` for disposable local/CI runs.
- No public product API, OpenAPI contract, pricing rule, payment rule or entitlement rule is changed.

## Migrations

- None.
- E2E state is stored in temporary WordPress options named `sr_e2e_p0_run_<md5>` and cleaned by `tests/e2e/bootstrap-runtime.php`.

## Commands and Results

- `make e2e` -> exit 0, chromium and mobile-chrome P0 flows passed.
- `npm run e2e -- --project=chromium` -> exit 0, 1 passed.
- `npm run e2e -- --project=mobile-chrome` -> exit 0, 1 passed.
- `git diff --check` -> exit 0.
- `python3 tools/agent/validate_docs.py` -> exit 0.
- `make test` -> exit 0.

See `docs/evidence/SR-066/commands.log` for failed-first attempts and fixes.

## Security / Permission / Concurrency Checks

- Runtime is off by default and cannot load without `SR_E2E_ENABLED=1`.
- Runtime refuses non-local/non-development WordPress environments.
- REST calls require `x-sr-e2e-key`.
- Playwright covers concurrent chromium/mobile execution by using per-run option keys.
- Download step verifies token issue and 302 redirect without exposing private storage paths.

## Acceptance Coverage

- 游客浏览: Playwright opens the local WordPress E2E page and verifies the public resource title/state.
- 下单: E2E runtime creates a real EDD pending order and order item.
- 提交凭证: browser action submits proof state through the WordPress REST runtime.
- 审核: browser action approves review and completes the EDD order.
- 下载: browser action issues a token, clicks the download link and observes the 302 redirect result.
- 退款: browser action refunds the EDD order and verifies entitlement revocation state.
- 失败截图/trace: Playwright config retains screenshot/video/trace on failures.
- CI 可重复运行: `.github/workflows/ci.yml` adds a Playwright P0 E2E job using `make e2e`.

## Known Limitations

- The E2E runtime is a local/CI test harness, not a production product endpoint.
- Current Bedrock/Nginx local runtime exposes REST reliably through `/?rest_route=...`; the test intentionally uses that path.
- Playwright HTML/trace artifacts are ignored in git and uploaded by CI as artifacts.

## Rollback

- Revert this branch to remove root E2E tooling, CI job, gated runtime loader and `tests/e2e/**`.
- No database migration rollback is required. Temporary E2E options are safe to delete by prefix `sr_e2e_p0_run_`.

## Next Safe Task(s)

1. Independent QA review for SR-066.
2. After QA PASS, mark SR-066 VERIFIED and proceed to SR-069 security testing.

## Commit / PR

- Commit: local branch commit; final SHA is reported in handoff after amend.
- PR: https://github.com/3300160875/Indicator_Marketplace/pull/93
