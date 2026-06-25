# Runtime Wiring — Review Report

- Scope: reviewed the bridge from existing SR-011/SR-012/SR-013 framework classes into the `sr-core` plugin entry path.
- Startup boundary: `Plugin::boot()` remains small; WordPress globals are isolated behind `RuntimeEnvironment`.
- Dependency behavior: runtime hooks are not registered unless required plugin/class dependencies pass.
- Taxonomy behavior: default taxonomies are registered on `init` for EDD `download` objects and skip names WordPress already knows.
- REST behavior: `rest_post_dispatch` attaches `X-Request-ID` to response objects with a `header()` method and falls back to sending a PHP header when needed.
- WP-CLI behavior: `wp sr migrate`, `wp sr status` and `wp sr schema:verify` command names are registered only when WP-CLI is available.
- Independent review follow-up: fixed real entry autoload coverage so `sr-core.php` can load local Core and Platform classes in the current Bedrock package layout.
- Independent review follow-up: fixed WP-CLI callback shape so callbacks accept positional args plus associative options and pass options into `MigrationCommand::migrate()`.
- Residual risk: concrete DB migration persistence is still deferred to later schema tasks; current CLI wiring uses an empty migration set by design.
