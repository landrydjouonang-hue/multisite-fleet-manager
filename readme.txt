=== Multisite Fleet Manager ===
Contributors: landrydjouonang
Tags: multisite, network, network admin, site management, updates
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
Network: true
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage a WordPress Multisite network from one console: site inventory, health indicators, plugins and themes, users, reports and an audit log.

== Description ==

Multisite Fleet Manager gives network administrators one place to see every site of a WordPress Multisite network — its status, health, theme, plugins, users and content — and to act on it through a single audited pipeline.

**Requires WordPress Multisite.** On a single-site installation the plugin refuses to activate and explains why. It never emulates a network. It must be network activated.

= Screens =

* **Dashboard** — sites by status, network facts, theme usage, recently registered and recently updated sites, and a one-click discovery run.
* **Site Dashboard** — a fleet console with one row per site: status, health, WordPress version, active theme and update counts, with search, filters, sorting, pagination and expandable per-site details.
* **Health** — how each of the twenty-one indicators scores across the fleet, grouped as Reliability, Security, Performance and Updates. Every count links to exactly those sites.
* **Plugins & Themes** — what is installed network-wide, versions, available updates and where each one runs, plus centralized update controls and per-site plugin activation.
* **Users** — every account with its roles, sites, super-administrator status and last activity. Super administrators can add users to sites, change their roles and remove them.
* **Reports** — total sites, sites needing updates, sites with errors, plugin and theme update counts and the health distribution, each with its change since the previous snapshot; CSV exports, a printable report and historical snapshots for trends.
* **Operation Log** — an append-only audit trail of every update, activation, membership and settings change, including attempts that were refused.
* **Settings** — discovery schedule and batch size, log and snapshot retention, e-mail notifications, indicator thresholds, and delegated access for non-super-admins.
* **Diagnostics** — environment, self-checks, REST routes and hooks, and a copy-paste report that contains no site names, addresses or secrets.
* **Dashboard widget** — the fleet at a glance on the Network Admin dashboard.

= How it works =

Read-only site discovery walks the network in resumable batches, daily in the background and on demand. Every screen then reads that stored inventory, so what a page costs depends on the page, not on how many sites you have: on a 507-site inventory the Site Dashboard costs six database queries and never switches site.

Every state change — from the browser or from a form without JavaScript — passes through one pipeline: capability check (the plugin's own and core's), a per-operation nonce, target validation, a network-wide lock, then an entry in the audit log whatever the outcome.

= Not a security scanner =

The health indicators cover operational health: versions, updates, configuration and capacity. Multisite Fleet Manager does **not** scan for malware, modified files, vulnerable code, weak passwords or intrusions, and a clean result does not mean a site is secure. The security group says so on every screen it appears on.

= Privacy =

No outbound HTTP requests, no telemetry, no third-party services. Passwords, password hashes, activation keys, session tokens and application passwords are never read into the interface, the REST API, exports or the log. E-mail addresses are shown to super administrators only.

== Installation ==

1. Make sure your installation is a Multisite network.
2. Upload the `multisite-fleet-manager` folder to `/wp-content/plugins/`, or upload the ZIP in Network Admin → Plugins → Add New.
3. Go to Network Admin → Plugins and click "Network Activate".
4. Open Network Admin → Fleet Manager and run site discovery.

== Frequently Asked Questions ==

= Does it work on a single site? =

No. It requires WordPress Multisite and explains this if you try to activate it on a single site.

= Does discovery change my sites? =

No. Discovery only reads: site options, counts and the update data WordPress already keeps. Nothing is written to your sites.

= Will it slow down a large network? =

Screens read the stored inventory instead of visiting each site, and discovery is batched with a time budget and resumes in the next request. On a 507-site inventory every screen renders in well under a second; the batch size and schedule are configurable in Settings.

= Can non-super-admins use it? =

Yes, for reading. The seven read-only capabilities (dashboard, sites, discovery, plugins and themes, log, users, reports) can be delegated per user from Settings → Delegated access. The capabilities that change files, users or settings stay with super administrators and cannot be delegated.

= Can it produce a PDF? =

Yes, through your browser: open the printable report and choose "Save as PDF" in the print dialog. No PDF library is bundled, so nothing extra is installed on your server.

= Is this a security scanner? =

No. See "Not a security scanner" above.

= Does it show passwords? =

No. Multisite Fleet Manager never reads or shows passwords, password hashes, activation keys, sign-in tokens or application passwords.

= Can it manage other WordPress installations? =

No. Everything is scoped to the networks of this installation. There is no remote management of outside sites.

= What happens when I uninstall it? =

Deactivation keeps the inventory and only clears the scheduled events. Uninstalling drops the plugin's four tables, deletes its options on every network, removes its user meta and clears its cron events.

== Screenshots ==

1. Site Dashboard: the fleet console, one row per site, with health indicators and update counts.
2. Dashboard: status counts, network facts and recent activity.
3. Health: the twenty-one indicators grouped by concern, with fleet-wide counts.
4. Plugins & Themes: what is installed, where it runs and what can be updated.
5. Users: accounts, roles, sites and last activity.
6. Reports: headline figures with deltas, pending updates and trends.
7. Operation Log: the audit trail, including refused attempts.
8. Settings: discovery, retention, notifications, thresholds and delegated access.

== Changelog ==

= 1.0.0 =
* Network settings screen: discovery schedule and batch size, log and snapshot retention, notification preferences and indicator thresholds. Saved settings feed the plugin's own filters, so code always wins.
* Delegated access UI: grant or revoke the read-only capabilities per user, with the managing capabilities still reserved for super administrators.
* E-mail notifications: a daily or weekly fleet digest, and an immediate message when an operation fails.
* Network Admin dashboard widget with the fleet summary.
* Developer diagnostics screen: environment, self-checks, REST routes, hooks, and a shareable report with no names or secrets.
* Export tools extended with user and operation-log CSVs, plus a plain-text diagnostics download.
* Contextual help on every screen.
* Tightened the REST operations endpoint: a signed-in user who cannot run any operation is now refused before the pipeline, instead of being logged as denied.
* Hardened the notification recipient list against pasted mail headers.
* List screens no longer resolve each site's admin URL through `switch_to_blog()`: on a 500-site network the Site Dashboard dropped from 108 queries and 40 site switches to 6 queries and none.

= 0.7.0 =
* Developer-focused performance and security indicators, grouped by category.

= 0.6.0 =
* Network-wide reporting with CSV exports, a printable report and historical snapshots.

= 0.5.0 =
* Site health indicators and fleet health summaries.

= 0.4.0 =
* Centralized network user visibility and user/site management.

= 0.3.0 =
* Plugin and theme visibility, centralized updates and the operation log.

= 0.2.0 =
* Network site dashboard with health indicators and update counts.

= 0.1.0 =
* Multisite foundation: detection, Network Admin menu, network capabilities, site discovery, site repository, dashboard.

== Upgrade Notice ==

= 1.0.0 =
First stable release. Adds network settings, delegated access, notifications, a dashboard widget, diagnostics and more exports, and makes the list screens markedly cheaper on large networks. No database upgrade is required.
