
# Changelog

All notable changes to Multisite Fleet Manager. Dates are UTC; versions follow [semantic versioning](https://semver.org/).

## 1.0.0 — 2026-10-07 — First stable release

### Added

- **Network settings** (`wpfleet_settings`): discovery recurrence and batch size, operation-log and snapshot retention, snapshot interval, notification preferences and every indicator threshold. Settings are applied by *providing* the plugin's own filters at priority 10, so a filter in code always beats the screen, and the stored shape is exactly the defaults — unknown keys are dropped, numbers clamped, versions and schedules validated.
- **Delegated access UI**: grant or revoke the seven read-only capabilities per user by ID, login or e-mail. The managing capabilities remain non-delegatable, super admins are refused as targets (they already hold everything), and a grant always includes the dashboard capability so the menu appears.
- **Notifications**: a daily or weekly fleet digest and an immediate message when an operation fails, in plain text, to the configured addresses or the network admin address. Off by default; the digest event lives on the network's main site and is rescheduled when the setting changes. The digest repeats the "not a security assessment" scope note.
- **Dashboard widget**: fleet summary with health counts, pending updates and the last discovery, on the Network Admin dashboard and the main site's dashboard, capability-gated like every other surface.
- **Developer diagnostics**: environment, plugin and schema state, self-checks (tables, schema version, scheduled discovery, inventory, update environment, WP-Cron), every REST route with the capability it needs, and every action and filter with its description. The copy-paste report contains no site names, addresses, e-mail addresses or secrets.
- **Export tools**: user and operation-log CSVs alongside the existing summary, sites, updates and snapshots exports, plus a plain-text diagnostics download (which needs `wpfleet_manage_settings`, not just report access).
- **Contextual help** on every screen, with the scope caveat in the sidebar.
- `Site::admin_url()`, built from the stored address.
- `OperationService::can_any()`, for permission callbacks that gate the pipeline itself.

### Changed

- `POST /operations` now requires the caller to hold the capabilities of at least one operation. Previously any signed-in user reached the pipeline and was refused inside it, which meant an unprivileged account could fill the audit log with denials.
- The notification recipient list is split on whitespace and semicolons as well as commas. `sanitize_email()` would otherwise fuse a pasted header-injection attempt into one plausible-looking address, and the intended recipient would silently stop receiving mail.
- The Site Dashboard and Site Inventory build each row's site-dashboard link from the stored address instead of core's `get_admin_url( $blog_id )`, which resolves it with `switch_to_blog()`. On a 500-site inventory the console went from 108 queries and 40 site switches to **6 queries and none**; the inventory table from 73 to 3.
- The settings closures read their values when the filter runs, so a save takes effect in the same request rather than the next one.

### Testing

Nine suites, **577 assertions**, against a real seven-site network: the seven feature suites plus dedicated Multisite-compatibility, permission, security and performance suites. See `docs/TESTING.md`.

## 0.7.0 — 2026-10-06 — Phase 7: Developer indicators by category

- Six new indicators, for twenty-one in total. Performance: **memory limits**, **database size** per site, **active plugin count** (site plus network-activated). Security: **file editing configuration**, **administrator signals** (administrators per site, sites with none, default `admin` login), **outdated components**.
- Indicators are grouped into Reliability, Security, Performance and Updates, shown as groups on the Health screen, under each site's details, and carried in the REST payload. Third-party checks can declare a group through `wpfleet_health_check_category`.
- The security group states on every screen that it is a set of configuration indicators, not a security assessment: no malware, file-change, vulnerability, password or intrusion detection, and a clean result is not evidence a site is safe. The outdated-components message adds that it cannot tell which updates are security fixes.
- Schema 7 stores the administrator count per site.
- Fixed: `wpfleet_outdated_components_limit` was overridden by an internal floor.

## 0.6.0 — 2026-10-06 — Phase 6: Network reporting

- New **Reports** screen: total sites, sites needing updates, sites with errors, plugin and theme update counts and the health distribution, each with its share of the fleet and the change since the previous snapshot.
- Health distribution as a bar and a table, the indicators failing on the most sites, every pending plugin and theme update with sites affected, and the snapshot history.
- Four CSV exports (summary, sites, updates, snapshots), nonced and capability-checked, with a UTF-8 BOM and protection against spreadsheet formula injection.
- Printable report: plain, self-contained, with print styles. A PDF is one browser "Save as PDF" away; no PDF library is bundled.
- Historical snapshots (schema 6): captured automatically after discovery at most twice a day, or on request through the audited operation pipeline, pruned after a year. They drive the trend lines and the deltas.
- New capability `wpfleet_view_reports` (grantable) and REST routes `/reports`, `/reports/updates`, `/reports/snapshots`, `/reports/snapshots/{id}`.

## 0.5.0 — 2026-10-01 — Phase 5: Site health indicators and summaries

- Eight new indicators join the seven from Phase 2, for fifteen in total: WordPress version, PHP version, database server, HTTPS, scheduled tasks, debug mode, autoloaded options and storage.
- New **Health** screen: fleet roll-up, a per-indicator table counting sites in each state with coverage bars, the shared environment (WordPress, PHP, database, cron, debugging, disk, HTTPS coverage, database size) and the sites to look at first.
- Every indicator count links to the Site Dashboard filtered to those sites; the console gained an indicator filter and chip.
- Schema 5 stores the new per-site measurements: scheduled events and how overdue they are, autoloaded options size, database size, and upload space used and allowed.
- Database sizes are read once per discovery pass and attributed by prefix; the main site's figure excludes sub-site and installation-wide tables.
- Anything that cannot be measured (disk space on restricted hosts, quotas on networks without them) is reported as unknown and never counts against a site.
- Clearly stated scope, on screen and in the API: operational health only, not a security scan.
- REST: `GET /health`.

## 0.4.0 — 2026-09-23 — Phase 4: Network users

- New **Users** screen: every network account with its roles, sites, super-administrator status, last activity and registration date, plus search, filters (role, site, super admins, users without a site, spam or deleted), sorting and pagination.
- Per-user view with the sites they belong to; super administrators can add a user to a site with a role, change that role, or remove them. A site's last administrator cannot be removed or demoted.
- Last activity: logins are recorded from now on (site included) and also derived from open sessions, with the source always stated. WordPress itself keeps no last-login date.
- Membership index (`wpfleet_user_sites`, schema 4) built by discovery and kept current by core's user hooks. Sites above `wpfleet_user_index_limit` members are skipped and reported.
- No credentials anywhere: no passwords, hashes, activation keys, session tokens or application passwords. E-mail addresses are limited to super administrators.
- Account creation, editing, deletion and super-admin changes stay with core's Network Admin → Users.
- REST: `/users`, `/users/{id}`, and three new operations on `POST /operations`.

## 0.3.0 — 2026-09-23 — Phase 3: Plugins, themes and centralized updates

- New **Plugins & Themes** screen: installed plugins and themes, versions, available updates (with compatibility notes), network activation, per-site activation counts and auto-update state, with search, filters, sorting and pagination.
- Per-site view of plugins and theme, with activate/deactivate controls. A plugin whose files are missing can be deactivated, clearing the "Plugin files" health error.
- Centralized updates through core's `Plugin_Upgrader` / `Theme_Upgrader`, one at a time or in bulk, only where they are safe: file modifications allowed, direct filesystem access, no maintenance mode, package present and compatible. Fleet Manager never updates itself.
- One `OperationService` enforces permission (plugin + core capability), a per-operation nonce, target validation (site belongs to this network; plugin/theme installed and eligible) and logging, for both REST and no-JavaScript forms. A network-wide lock prevents concurrent operations.
- New **Operation Log** screen and `wpfleet_operations` table (schema 3): an append-only audit trail of succeeded, failed, rejected and denied operations, with filters, search and pagination, purged after 180 days.
- Site Dashboard: new "running plugin" filter and a link to each site's plugins and theme.
- REST: `/plugins`, `/themes`, `/sites/{id}/extensions`, `GET|POST /operations`.

## 0.2.0 — 2026-09-22 — Phase 2: Network site dashboard

- New **Site Dashboard** fleet console with the columns Site, Status, Health, WordPress, Active theme, Plugin updates and Theme updates, plus search, filters (health, status, updates, theme), filter chips, sorting on every column (health by severity), pagination and per-page choice. It works without JavaScript.
- KPI tiles: sites, Healthy, Needs Attention, Error, plugin and theme updates. Each tile is a one-click filter.
- Health engine: seven read-only checks, Healthy / Needs Attention / Error roll-up, extensible through `wpfleet_health_checks`.
- Per-site plugin and theme update counts from WordPress's own update data. The fleet is re-scored automatically when that data changes.
- Schema 2 adds `theme_template`, `active_plugin_list`, `plugin_updates`, `theme_updates`, `health`, `health_indicators` and `health_checked_at`. An automatic upgrade queues a rediscovery.
- REST: `/sites` accepts `health`, `updates` and `theme` filters and returns described indicators. `/summary` returns health and update totals.
- The no-JavaScript discovery form can return to the console.

## 0.1.0 — 2026-09-22 — Phase 1: Multisite foundation

- Explicit Multisite detection (`MultisiteGuard`). Single-site and per-site activation are refused with an explanation. When dormant, the plugin shows a notice and never emulates a network.
- Network Admin menu: Fleet Manager → Dashboard, Site Inventory.
- Network capabilities (`wpfleet_view_dashboard`, `wpfleet_view_sites`, `wpfleet_run_discovery`, `wpfleet_manage_access`) with network-wide delegation.
- Site discovery: batched keyset-cursor runs with a lock, stale-row cleanup, a daily cron with resume, and a no-JavaScript fallback.
- Real-time inventory sync on site lifecycle, option, membership and publish changes.
- Global `wpfleet_sites` table and `SiteRepository` (filter, search, sort, aggregates).
- Dashboard: status counts, network facts, theme usage, recent sites.
- REST API `multisite-fleet-manager/v1`: sites, summary, discovery.
- Clean deactivation (data kept) and uninstall (table, options on all networks, cron).
