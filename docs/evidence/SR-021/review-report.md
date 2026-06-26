# SR-021 Review Report

- Review type: structured local review fallback
- Reason: delegated review agents are currently unavailable because the account usage limit was reached during SR-017.
- Reviewed at: 2026-06-26
- Scope: design tokens, component CSS and PHP components under the SR-021 allowed theme paths.

## Findings

### Important 1 — `@import` ordering in theme CSS

The first local review found that `assets/css/theme.css` had a `:root` rule before the new imports. CSS imports should appear before ordinary rules.

Resolution:
- Moved `@import url("./tokens.css");` and `@import url("./components.css");` to the top of `theme.css`.
- Kept the existing `:root { color-scheme: light; }` rule after the imports.

### Important 2 — Component states and accessibility

The review checked that components have explicit normal, empty, error and disabled states and visible keyboard focus styles.

Resolution:
- `sr_theme_button()` renders normal links and disabled non-link spans with `aria-disabled="true"`.
- `sr_theme_notice()` renders empty/status and error/alert variants.
- `sr_theme_resource_card()` renders normal and disabled states.
- Component CSS uses `outline: var(--sr-focus-ring)` for keyboard focus.

### Scope Review

- Production changes are limited to `web/app/themes/stock-resource-theme/assets/**` and `web/app/themes/stock-resource-theme/components/**`.
- No templates, REST controllers, WordPress hooks or direct database access were introduced.
- Components escape output with `htmlspecialchars`.
- Root `npm run test/build` remain unavailable, so theme-local alternatives are documented and passing.

## Review Outcome

The local review findings were addressed and covered by automated evidence:
- `php docs/evidence/SR-021/theme-components-check.php`
- `npm --prefix web/app/themes/stock-resource-theme run test`
- `npm --prefix web/app/themes/stock-resource-theme run build`
- `npm run lint`
