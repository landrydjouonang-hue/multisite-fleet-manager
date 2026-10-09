# Multisite Fleet Manager

Central management console for **WordPress Multisite** networks: one place to see every site's status, health, plugins, themes, users and updates — and to act on them through a single audited pipeline.

[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green)](LICENSE)

Author: **Djouonang Landry** · Version 1.0.0 · Requires WordPress 6.4+, PHP 8.0+, **Multisite**

---

## What it does

| Screen | What you get |
|---|---|
| **Dashboard** | Status counts, network facts, theme usage, recently registered and recently updated sites, and a one-click discovery run |
| **Site Dashboard** | A fleet console: one row per site with status, health, WordPress version, active theme and update counts, plus search, filters, sorting, pagination and expandable per-site details |
| **Health** | How each of the 21 indicators scores across the fleet, grouped into Reliability, Security, Performance and Updates, with every count linking to exactly those sites |
| **Plugins & Themes** | Everything installed, where it runs, what can be updated — and centralized update controls where they are safe and supported |
| **Users** | Every account, its roles, its sites and its last activity; add, remove and re-role users per site |
| **Reports** | Headline figures with deltas, pending updates, trends from stored snapshots, CSV exports and a printable report |
| **Operation Log** | An append-only audit trail of every state change, including the ones that were refused |
| **Settings** | Discovery, retention, notifications, indicator thresholds and delegated access |
| **Diagnostics** | Environment, self-checks, REST routes and hooks, plus a copy-paste report that contains no names or secrets |
| **Dashboard widget** | The fleet at a glance on the Network Admin (and main site) dashboard |

### Scope, stated plainly

These are **operational** indicators: versions, updates, configuration and capacity, read from the network itself.

**This is not a security scan.** Multisite Fleet Manager does not look for malware, modified files, vulnerable code, weak passwords or intrusions, and a clean result here does not mean a site is secure. Use a dedicated security product for that. The same wording appears on every screen that shows a security indicator, and in the REST payload.

It also does not manage *other* installations: everything is scoped to the networks of this WordPress install.

## Multisite only

The plugin works only on a real WordPress Multisite network, and only when it is **network activated**. It never emulates a network.

| Environment | Behaviour |
|---|---|
| Single site | Activation is refused with an explanation. If it is already active (a network converted back, say), it stays dormant and shows a notice. |
| Multisite, activated on one site | Activation is refused; the notice asks for network activation. |
| Multisite, network activated | Fully operational in **Network Admin → Fleet Manager**. |

## Install

1. Copy the plugin folder to `wp-content/plugins/multisite-fleet-manager`, or upload the ZIP in **Network Admin → Plugins → Add New**.
2. **Network activate** it.
3. Open **Network Admin → Fleet Manager** and run **Discover sites**. Discovery then runs daily, and real-time hooks keep the inventory current in between.

Nothing is written to your sites: discovery reads options and counts, and nothing else.

## How it works

Every screen reads a stored **inventory** rather than walking the network, so cost depends on the page you are looking at, not on how many sites you have. Measured on a 507-site inventory: the Site Dashboard costs 6 queries and no `switch_to_blog()`, the inventory table 3, and the fleet-wide health summary 2.

```
discovery (batched, resumable)  ──►  inventory tables  ──►  screens, REST, reports, exports
        ▲                                   ▲
   WP-Cron + manual run            real-time core hooks
```

Every state change — from the browser or from a form without JavaScript — goes through one pipeline:

1. **Permission**: the Fleet Manager capability *and* core's own (`update_plugins`, `activate_plugins`, `manage_network_users`, …), which Multisite reserves for super admins.
2. **Nonce**: a per-operation nonce (`wpfleet_op_{operation}`); REST calls also need the standard REST cookie nonce.
3. **Target validation**: the site must exist on *this* network; the plugin, theme, user or role must exist and be allowed.
4. **Lock**: one operation at a time per network.
5. **Logging**: every outcome — succeeded, failed, rejected, denied — is written to the audit log, including operations cut short by a fatal error.

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the full design and [docs/DATABASE.md](docs/DATABASE.md) for the tables, options and cron events.

## Health indicators

Twenty-one indicators, grouped. Each is a pure function over the stored snapshot plus shared network facts, so re-scoring the whole fleet is one cheap pass.

**Reliability** — site database, active theme, plugin files, site address
**Updates** — WordPress version, site database version, database server, plugin updates, theme updates
**Performance** — PHP version, memory limits, scheduled tasks, autoloaded options, database size, active plugin count, storage
**Security indicators** — HTTPS, debug mode, file editing configuration, administrators, outdated components

Roll-up: any error → **Error**; otherwise any warning → **Needs Attention**; otherwise **Healthy**. Anything that cannot be measured reports as *unknown* and never counts against a site.

Add your own with `wpfleet_health_checks`: implement `FleetManager\Health\Contracts\HealthCheck`, don't switch sites or make HTTP requests, and declare a group with `wpfleet_health_check_category`. A check that throws becomes *unknown* instead of breaking the console.

## Capabilities

| Capability | Grants | Delegatable |
|---|---|---|
| `wpfleet_view_dashboard` | Open the dashboard | yes |
| `wpfleet_view_sites` | Browse the site inventory | yes |
| `wpfleet_run_discovery` | Start site discovery | yes |
| `wpfleet_view_extensions` | Browse plugins and themes | yes |
| `wpfleet_view_log` | Read the operation log | yes |
| `wpfleet_view_users` | Browse network users | yes |
| `wpfleet_view_reports` | View and export network reports | yes |
| `wpfleet_manage_updates` | Update plugins and themes | **no** |
| `wpfleet_manage_plugins` | Activate/deactivate plugins on a site | **no** |
| `wpfleet_manage_users` | Add/remove users on sites, change roles | **no** |
| `wpfleet_manage_access` | Delegate access | **no** |
| `wpfleet_manage_settings` | Change network settings | **no** |

Super admins hold everything. The read-only capabilities can be delegated network-wide from **Settings → Delegated access**, or in code:

```php
FleetManager\Core\Capabilities::grant( $user_id, array( 'wpfleet_view_sites', 'wpfleet_view_reports' ) );
FleetManager\Core\Capabilities::revoke( $user_id );
```

The managing capabilities are never delegatable: they change files or behaviour for the whole network, so they stay with super admins. Delegation is stored per network and removed when a user is deleted.

## REST API

Namespace `/wp-json/multisite-fleet-manager/v1`. Every route has a real permission callback; reads are capability-gated and the single write route requires the capabilities of the operation it is asked to run, plus a nonce.

| Method | Route | Capability |
|---|---|---|
| GET | `/sites?status=&health=&updates=&theme=&search=&orderby=&order=&page=&per_page=` | `wpfleet_view_sites` |
| GET | `/sites/{blog_id}` | `wpfleet_view_sites` |
| GET | `/summary` | `wpfleet_view_dashboard` |
| GET | `/health` | `wpfleet_view_dashboard` |
| GET / POST | `/discovery`, POST `/discovery/{run}/batch` | `wpfleet_run_discovery` (GET: dashboard) |
| GET | `/plugins`, `/themes`, `/sites/{id}/extensions` | `wpfleet_view_extensions` |
| GET | `/users`, `/users/{id}` | `wpfleet_view_users` |
| GET | `/reports`, `/reports/updates`, `/reports/snapshots`, `/reports/snapshots/{id}` | `wpfleet_view_reports` |
| GET | `/operations?operation=&status=&site_id=` | `wpfleet_view_log` |
| POST | `/operations` (`operation`, target, `nonce`) | the operation's own capabilities |

`admin_email` is returned to super admins only. Passwords, hashes, activation keys and session tokens never appear in any payload, export or log.

## Hooks

**Actions** — `wpfleet_loaded`, `wpfleet_discovery_started`, `wpfleet_discovery_completed`, `wpfleet_operation_succeeded`, `wpfleet_operation_logged`, `wpfleet_snapshot_captured`, `wpfleet_settings_saved`

**Filters** — `wpfleet_health_checks`, `wpfleet_health_check_category`, `wpfleet_inspected_site`, `wpfleet_user_capabilities`, `wpfleet_discovery_batch_size`, `wpfleet_discovery_recurrence`, `wpfleet_user_index_limit`, `wpfleet_snapshot_interval`, `wpfleet_snapshot_retention_days`, `wpfleet_operation_log_retention_days`, `wpfleet_measure_upload_space`, `wpfleet_cron_grace_period`, `wpfleet_cron_stuck_after`, `wpfleet_autoload_limits`, `wpfleet_database_size_limits`, `wpfleet_active_plugin_limits`, `wpfleet_memory_limits`, `wpfleet_storage_limits`, `wpfleet_recommended_php`, `wpfleet_recommended_database`, `wpfleet_many_administrators`, `wpfleet_outdated_components_limit`

The settings screen *provides* these filters at priority 10, so code always wins:

```php
// Beats whatever is saved in Settings.
add_filter( 'wpfleet_discovery_batch_size', fn() => 100, 20 );
```

**Network Admin → Fleet Manager → Diagnostics** lists every hook and route with its description, so the reference is in the product, not only here.

## Development

```
multisite-fleet-manager.php   bootstrap: requirements → autoloader → Multisite gate → Plugin::boot()
src/
  Core/          Requirements, MultisiteGuard, Capabilities, Settings, Activator/Deactivator/Upgrader/Uninstaller
  Network/       read-only network facts
  Sites/         Site, SiteRepository, SiteInspector, SiteDiscovery, SiteSync, DiscoveryScheduler, TableSizes
  Health/        Health, Indicator, Category, FleetContext, registry, evaluator, refresher, Checks/
  Extensions/    installed plugins and themes, and where they run
  Operations/    Operation, OperationService, OperationLog, UpdateEnvironment, Handlers/
  Users/         UserIndex, UserDirectory, UserActivity, UserSync
  Reporting/     ReportBuilder, SnapshotService, SnapshotRepository, CsvExporter
  Notifications/ Notifier (digest + failed-operation mail)
  Rest/          five controllers
  Admin/         Admin (menu/assets), one class per screen, Actions, Widgets, Help, View
templates/admin/ markup, kept out of the classes
assets/          admin.css + three small, optional scripts
docs/            architecture, database, testing, demo
```

- PSR-4 from `src/` through a tiny autoloader; **no runtime dependencies**, no Composer needed to run.
- PHP 8.0 as the floor: no enums, readonly properties or `never`.
- WordPress coding standards; `composer install && composer lint` runs PHPCS with WPCS and a PHP 8.0+ compatibility check.

```bash
composer install     # dev tooling only (PHPCS, WPCS, PHPCompatibility)
composer lint        # check
composer lint:fix    # fix what can be fixed automatically
```

## Testing

Twelve suites, **793 assertions**, run against a real Multisite network with seven sites in assorted states, plus an `E_ALL` pass over 66 paths that must come back silent. See [docs/TESTING.md](docs/TESTING.md) for what each one covers and how to run them.

| Suite | Covers |
|---|---|
| Feature suites (5) | Foundation, console, plugins and themes, users, health, reporting, developer indicators, 1.0 features |
| Multisite compatibility | Global vs per-site tables, blog-switch discipline, non-main-site contexts, lifecycle hooks, cron placement |
| Permissions | Five identities against every screen, REST route, operation and form action |
| Security | Nonces, SQL injection, output escaping, CSV formula injection, target validation, secret exposure, static source scan |
| Performance | Query counts and wall time at 7 and 507 sites, index coverage, memory, discovery budget |
| Guidelines | WordPress.org plugin guidelines, checked mechanically over the shipped source |
| Submission | Plugin Check rules over the built package; see [docs/SUBMISSION.md](docs/SUBMISSION.md) |
| Single site | What a reviewer sees first: activation refused with an explanation, nothing written, no fatal |

## Demo

[DEMO.md](DEMO.md) walks through the product as a portfolio piece: what to show, in what order, and the engineering decisions behind each screen.

## Privacy

- No outbound HTTP requests. The plugin reads update data WordPress already has; it never contacts WordPress.org itself.
- No telemetry, no phone-home, no bundled third-party services.
- Passwords, password hashes, activation keys, session tokens and application passwords are never read into the interface, the REST API, exports or the log.
- E-mail addresses are shown to super administrators only; the diagnostics report contains no site names, addresses or e-mail addresses.

## Uninstall

Deactivation keeps the inventory and only clears the scheduled events and any run in progress. Uninstalling drops the four tables, deletes the plugin's options on every network, removes its user meta and clears its cron events.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
