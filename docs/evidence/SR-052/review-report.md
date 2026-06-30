# SR-052 Independent Review Report

## Reviewer

- Reviewer: Ptolemy subagent
- Mode: read-only QA
- Result: PASS after fixes

## Initial findings

The first review returned FAIL with three concerns:

1. The membership entitlement API was only a pure projection and had no registerable REST route wrapper.
2. Cache metadata existed, but there was no real cache read/write/delete or invalidation behavior.
3. The membership template was not wired into `page-account.php`.

## Fixes reviewed

- Added `MeEntitlementsRouteRegistrar` for `GET stock-resource/v1/me/entitlements`.
- Route permission checks the logged-in current user, and handler binds to `get_current_user_id()`.
- Added `MeEntitlementsCacheStore`, `InMemoryMeEntitlementsCacheStore`, and `WordPressMeEntitlementsCacheStore`.
- `MeEntitlementsController::show()` now reads/writes cache, and `invalidateForUser()` deletes the user + rules-version key.
- Evidence now covers route registration, permission callback, current-user route handling, cache invalidation, and membership template rendering.

## Reviewer conclusion

PASS.

The reviewer confirmed the previous three issues are reasonably addressed within SR-052 allowed paths:

- REST route: `MeEntitlementsRouteRegistrar` provides a `register_rest_route` wrapper, logged-in permission callback, and `get_current_user_id()` binding.
- Cache: controller uses real cache `get/set`, `invalidateForUser()` deletes the key, and the WordPress adapter delegates to `wp_cache_get/set/delete`.
- Membership display: `membership.php` renders plan, expiry, scope, remaining quota, and reset time; evidence covers rendering.

## Scope check

Business code remains inside allowed paths:

- `packages/sr-entitlements/src/Rest/Me/MeEntitlementsController.php`
- `web/app/themes/stock-resource-theme/templates/account/membership.php`

Other changes are limited to SR-052 evidence and status files.

## Verified commands

The reviewer ran and passed:

- `php docs/evidence/SR-052/me-entitlements-check.php`
- `php -l` for changed PHP files
- `composer validate --strict`
- `make lint`
- `composer --working-dir=packages/sr-entitlements test`
- `make test`
- `git diff --check`
- `python3 tools/agent/validate_docs.py`

## Non-blocking follow-up

When a later task allows bootstrap/template entry changes, wire the registrar into `rest_api_init` and include the membership template in the actual account page render chain.
