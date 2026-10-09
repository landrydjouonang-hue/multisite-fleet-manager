# Architecture

Namespace `FleetManager`, PSR-4 from `src/` through a small autoloader, with no Composer dependency at runtime. The code targets PHP 8.0: no enums, readonly properties or `never`.

## Boot sequence (`multisite-fleet-manager.php`)

1. `Core\Requirements`: PHP and WordPress versions. This file uses conservative syntax so older PHP gets a notice instead of a fatal error.
2. Autoloader, then activation and deactivation hooks. These are registered *before* the Multisite gate so that activation on a single site can be refused with an explanation.
3. `Core\MultisiteGuard`: `ready` only when `is_multisite()` is true and the plugin is in `active_sitewide_plugins`. Otherwise `Admin\RequirementNotice` explains the state and the plugin returns without booting. There is no emulation fallback.
4. `Plugin::boot()` on `plugins_loaded`: the composition root with lazily built services.

## Layers

```
src/
  Core/          Requirements, MultisiteGuard, Capabilities, Settings,
                 Activator, Deactivator, Upgrader, Uninstaller
  Network/       NetworkInfo: read-only network facts (install type, registration, counts)
  Sites/         Site (record), SiteStatus, SiteRepository, SiteInspector, SiteDiscovery,
                 SiteSync (real-time), DiscoveryScheduler (cron), TableSizes,
                 DiscoveryInProgressException
  Storage/       Schema (dbDelta, four global tables)
  Health/        Health, Indicator, Category, FleetContext, HealthCheckRegistry,
                 HealthEvaluator, HealthRefresher, Contracts/, Checks/ (21)
  Extensions/    ExtensionInventory: installed plugins and themes, and where they run
  Operations/    Operation, OperationException, OperationService, OperationLog,
                 UpdateEnvironment, Contracts/, Handlers/
  Users/         UserIndex, UserDirectory, UserActivity, UserSync
  Reporting/     ReportBuilder, SnapshotService, SnapshotRepository, CsvExporter
  Notifications/ Notifier
  Rest/          DiscoveryController, SitesController, ExtensionsController,
                 UsersController, ReportsController
  Admin/         Admin (menu/assets), one class per screen, Actions (no-JS forms),
                 SitesListTable, Widgets, Help, Notices, RequirementNotice, View
templates/admin/  one template per screen, plus partials/
assets/css/admin.css, assets/js/{dashboard,console,extensions}.js
docs/             ARCHITECTURE, DATABASE, TESTING, DEMO
```

Templates receive a prepared `$vars` array and do no querying; classes do no markup. The three scripts are progressive enhancement only — every screen, filter and action works without JavaScript.

## Site discovery

- `SiteInspector::inspect( $blog_id )` reads `wp_blogs` flags through `get_site()`. It then switches to the site once to read its options (name, URLs, admin e-mail, theme, active plugins, locale, db_version) and counts (members, published posts and pages). The previous site is always restored in `finally`. Nothing is written to the site.
- `SiteDiscovery` runs:
  - `start()` stores the run state in the `wpfleet_discovery_run` network option. A 5-minute lock prevents overlapping runs (REST returns 409).
  - `step()` processes the next batch using a **keyset cursor** (`blog_id > cursor`). Unlike an offset, this stays correct when sites are created or deleted mid-run.
  - When the last batch is short, the run completes: rows the run did not see are deleted (only rows older than the run start, so real-time writes during the run survive), and a summary is stored in `wpfleet_last_discovery`.
  - `run( $source, $budget )` resumes or starts a run within a time budget. Cron and the no-JavaScript form use it.
- `SiteSync` listens to `wp_initialize_site`, `wp_update_site`, `wp_delete_site`, watched option updates, membership changes and publish transitions. It refreshes affected sites once, on `shutdown`. It is idle until the first discovery run, so the plugin never shows a partial inventory as if it were complete.
- `DiscoveryScheduler` runs daily on the network's main site, because WP-Cron is per site on Multisite. It follows up with a one-off event when a run does not fit in one request.

## Health (Phase 2)

```
src/Health/
  Health.php               roll-up: healthy | attention | error
  Indicator.php            one check result: id + state (good|warning|error|unknown) + data
  Contracts/HealthCheck    id(), label(), evaluate(Site, FleetContext), message(Indicator)
  FleetContext.php         network-wide inputs, loaded once per pass from core caches
  HealthCheckRegistry.php  built-in checks + `wpfleet_health_checks` filter; describe() for display
  HealthEvaluator.php      fills update counts, indicators and roll-up on a Site
  HealthRefresher.php      re-scores all stored sites on shutdown when network inputs change
  Checks/                  SiteData, ActiveTheme, MissingPlugins, DatabaseVersion,
                           PluginUpdates, ThemeUpdates, SiteAddress
```

- **Pure checks.** Checks judge only the stored snapshot plus `FleetContext`: update transients, network-activated plugins, core DB version, and memoised plugin-file and theme lookups. They never switch sites or make HTTP requests, so re-scoring a large fleet is a single cheap pass.
- **Two evaluation paths.**
  - `SiteInspector` calls the evaluator after every inspection, whether from discovery or real-time sync.
  - `HealthRefresher` re-evaluates stored rows in batches of 200 when `update_plugins`, `update_themes` or `update_core` change, when network activations change, or after upgrades and deletions. It writes only rows whose health data changed.
- **Stored as data, rendered as text.** Indicators are stored as `{id, state, data}`. Messages come from the check at display time, so they follow the viewer's language.
- **Isolation.** An exception in a check produces an *unknown* indicator and is logged in its data. The console keeps working.

## Health indicators (Phase 5)

Fifteen checks now run, all still pure functions over the stored snapshot plus `FleetContext`:

- **Network scope** (same answer on every site, because the files and constants are shared): WordPress version, PHP version, database server, debug mode. `FleetContext` reads them once per pass — including `disk_free_space()` guarded against hosts that disable it.
- **Site scope**: site data, active theme, plugin files, site database version, plugin and theme updates, HTTPS, scheduled tasks, autoloaded options, storage, site address.

New measurements are taken during inspection, inside the single `switch_to_blog()` the inspector already performs: the `cron` option (events, how many overdue, how late the oldest is), the autoloaded options size, and — only when the network enforces quotas — upload space. `Sites\TableSizes` reads `information_schema` once per request and attributes tables by prefix, excluding sub-site and installation-wide tables from the main site's figure.

`SiteRepository::health_summary()` rolls the stored indicators up in batches (one pass, no site switching) into per-indicator counts plus environment totals, and `query()` gained an `indicator` + `state` filter so any count links to exactly those sites.

Scope is stated in the UI and in the REST payload: operational health, not a security scan.

## Site dashboard (console)

`Admin\ConsolePage` reads and whitelists GET parameters: `s`, `health`, `site_status`, `updates`, `theme`, `orderby`, `order`, `paged`, `per_page`. It builds sortable headers (with `aria-sort`) and URLs that keep the current state. `templates/admin/console.php` renders:

- a dark header band with the meta line, actions and KPI filter tiles;
- the filter toolbar, a result count and filter chips;
- the table with expandable detail rows and `paginate_links()` pagination.

`assets/js/console.js` is optional: it collapses detail rows behind toggles and adds a `/` shortcut to the search box. Filters apply only on submit, not on change, so they work with the keyboard. Below 782px the table becomes stacked cards.

## Extensions and operations (Phase 3)

```
src/Extensions/ExtensionInventory.php   installed plugins/themes + where they run + updates
src/Operations/
  Operation.php            operation names, outcome statuses, nonce actions
  OperationException.php   outcome + HTTP status + error code, carries its log entry ID
  OperationLog.php         append-only audit log (query, write, purge)
  OperationService.php     the only way to change state (see below)
  UpdateEnvironment.php    is updating safe and supported here?
  Contracts/OperationHandler.php
  Handlers/                PluginUpdate, ThemeUpdate, SitePlugin (activate/deactivate), Upgrade helpers
```

`OperationService::run()` is the single entry point for REST, forms and bulk runs. It checks capabilities (plugin + core), verifies the per-operation nonce, asks the handler to validate the target, takes a network-wide lock, executes, then logs the outcome. Every refusal is logged too, so a denied or forged attempt leaves a trace. A `register_shutdown_function` guard logs operations killed by a fatal error (a plugin's activation hook, for example) and releases the lock.

Handlers never check permissions or nonces: they cannot be reached without those checks, which keeps the security rules in one place.

Updates delegate to core's `Plugin_Upgrader` / `Theme_Upgrader` with `WP_Ajax_Upgrader_Skin`, the same path as core's own update screens (maintenance mode, unpacking and rollback included). Success is confirmed by re-reading the installed version, not by trusting the return value. The update transient is then corrected in place rather than cleared, so update counts stay right without a remote check — core re-checks WordPress.org on `upgrader_process_complete` anyway.

Per-site activation runs `activate_plugin()` / `deactivate_plugins()` inside `switch_to_blog()`, then refreshes that site's inventory record and health.

## Users (Phase 4)

```
src/Users/
  UserIndex.php      the wpfleet_user_sites index (build, update, query, role counts)
  UserDirectory.php  user rows with memberships, roles, activity and super-admin status
  UserActivity.php   records logins; derives last activity from open sessions
  UserSync.php       core user hooks → index
src/Rest/UsersController.php, src/Admin/UsersPage.php
src/Operations/Handlers/UserSiteHandler.php (add / remove / change role)
```

- **Indexing needs no site switching.** `usermeta` is global, so a site's memberships are one query on `{prefix}capabilities` keys. Discovery indexes each site it inspects; `UserSync` keeps the index correct in between.
- **The directory queries `wp_users` joined to the index** through `EXISTS` subqueries for the role and site filters, so filtering and sorting stay in SQL. Memberships, activity and site names are then fetched for the current page only.
- **Activity has two sources**, always labelled in the UI: a login recorded by Fleet Manager (`wpfleet_last_login`), or the login time carried by a valid session. Session tokens are authentication secrets: only counts and timestamps derived from them leave `UserActivity`.
- **Management reuses `OperationService`**, so permission, nonce, target validation and logging are identical to Phase 3. `UserSiteHandler` additionally validates the role against the *target site's* roles (roles are per site) and refuses to remove or demote a site's last administrator.

## Indicator categories (Phase 7)

`Health\Category` maps every check to Reliability, Security, Performance, Updates or Other, with labels, descriptions and the security caveat in one place. The map is filterable (`wpfleet_health_check_category`), so the `HealthCheck` contract did not change and third-party checks keep working — they simply land in "Other" until they declare a group.

`HealthCheckRegistry::by_category()` and `describe_by_category()` return the grouped forms used by the Health screen, the console's per-site details and the REST payload. The security caveat is rendered next to the security group everywhere it appears, rather than once in a corner.

Per-site administrator counts come from the same `{prefix}capabilities` meta the user index uses, counted during inspection (schema 7), so the administrators check stays a pure function like every other.

## Reporting (Phase 6)

```
src/Reporting/
  ReportBuilder.php        the figures every report view shares
  SnapshotRepository.php   wpfleet_snapshots storage and retention
  SnapshotService.php      capture (automatic + on request), trend series, deltas
  CsvExporter.php          rows per export type, and the streaming response
src/Admin/ReportsPage.php, templates/admin/reports.php, templates/admin/report-print.php
src/Rest/ReportsController.php
src/Operations/Handlers/SnapshotHandler.php
```

- **One model, four outputs.** `ReportBuilder::summary()` is the single source for the screen, the printable version, the CSV exports, the REST payload and what a snapshot stores. Figures come from the stored inventory, so a report costs a few aggregate queries rather than a pass over every site.
- **Exports are read-only but still guarded**: a nonce in the link plus `wpfleet_view_reports`. `CsvExporter::escape()` neutralises values a spreadsheet would execute as a formula — site and plugin names come from the network, so this is not theoretical.
- **The printable view is a separate template**, not CSS hiding, so what prints is deliberately chosen. PDF is left to the browser's print dialog rather than bundling a PDF library.
- **Snapshots** keep the headline figures as columns and the full summary as JSON: trends stay a simple query, while an old report can still be read back in full. Capture on request goes through `OperationService`, so it is logged like any other state change.

## Settings, notifications and diagnostics (1.0)

```
src/Core/Settings.php                     one network option; applies itself through the plugin's filters
src/Operations/Handlers/SettingsHandler.php  settings_update and access_update operations
src/Notifications/Notifier.php            digest event + failed-operation mail
src/Admin/SettingsPage.php, DiagnosticsPage.php, Widgets.php, Help.php
templates/admin/settings.php, diagnostics.php
```

- **Settings are filter providers, not a second source of truth.** `Settings::register()` hooks every configurable value onto the filter that already governed it (`wpfleet_discovery_batch_size`, `wpfleet_memory_limits`, …) at priority 10. Code at a later priority always wins, so a site-specific override in a `mu-plugin` is never silently replaced by what someone typed in the screen. Each closure reads the stored value *when the filter runs*, so a save applies within the same request.
- **Saving is an operation.** `settings_update` and `access_update` go through `OperationService` like any other state change: capability (`wpfleet_manage_settings` + `manage_network_options`, or `wpfleet_manage_access` + `manage_network_users`), nonce, validation, lock, audit entry. The log records *which keys changed*, never the values, so notification addresses and thresholds do not end up in an audit trail.
- **Sanitising is the schema.** `Settings::defaults()` defines the shape; `sanitize()` drops unknown keys, clamps numbers to documented bounds, validates the cron schedule against `wp_get_schedules()`, requires a version-shaped string for the PHP threshold, and reduces the recipient list to valid, unique addresses.
- **Notifications are opt-in and cheap.** The digest reads the same `ReportBuilder::summary()` every other report view uses; the failed-operation mail hangs off `wpfleet_operation_logged` and fires only for the `failed` status. Both are plain text, both end with the scope note, and the recurring event lives on the network's main site because cron is per site.
- **Diagnostics are derived, never stored.** `DiagnosticsPage` recomputes environment, self-checks, routes and hooks on each view. The downloadable report is deliberately name-free and secret-free so it can be pasted into a public issue; the security suite asserts that it contains no site addresses, e-mail addresses, hashes, salts or tokens.
- **Help lives in one class.** `Admin\Help::add( $screen )` adds the per-screen tab, the overview tab and the sidebar caveat, hooked on each page's `load-` action so core builds it before output.

## Capabilities

Roles are per site on Multisite, so network permissions are not stored on roles. Super admins pass every check (core behaviour). Other users receive grants from the `wpfleet_access` network option through `user_has_cap`. The filter exits immediately unless one of the plugin's own capabilities is being checked. `wpfleet_manage_access` is never grantable. Grants are removed when a user is deleted from the network.

## Cost model

The design claim is that a screen costs what the page costs, not what the network costs. Three rules keep it true, and the performance suite measures all three at 7 and 507 sites:

1. **Screens read the inventory, never the sites.** No `switch_to_blog()` on any list, summary, report or export path. Only `SiteInspector` (one switch per site, in `finally`) and the per-site operation handlers switch at all.
2. **Aggregates scan in batches.** `health_summary()` and `extension_usage()` walk stored rows with a keyset cursor, so a fleet-wide roll-up is a couple of queries rather than one per site.
3. **Nothing resolves a site through core per row.** `get_admin_url( $blog_id )`, `get_blogaddress_by_id()` and friends switch sites internally; `Site::admin_url()` uses the stored address instead. Removing one such call per row took the console from 108 queries and 40 site switches to 6 queries and none on a 500-site inventory.

Measured, 507 inventory rows: console 6 queries, inventory table 3, health 5, reports 20, users 11, fleet health summary 2, sites CSV 2, `GET /sites` 6 — every screen under 0.1 s and the whole suite under 48 MB.

## Scope

- Everything is scoped to the **current network** (`get_current_network_id()`), so multi-network installations are supported and kept isolated. The tables are installation-global, like `wp_blogs`, and every query carries a `network_id`.
- Toward sites, the plugin is read-only except through `OperationService`: the only writes are plugin and theme updates (core's upgraders), per-site plugin activation, user/site membership changes, snapshots and its own settings.
- There is no remote management of installations outside this WordPress install, and no outbound HTTP request of any kind.
