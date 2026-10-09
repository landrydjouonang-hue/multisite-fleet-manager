# Testing

Multisite Fleet Manager is tested against a **real WordPress Multisite network**, not a mocked one: the behaviour that matters here — global vs per-site tables, `switch_to_blog()` discipline, super-admin-only core capabilities, per-site cron — only exists on a real network.

## The test network

A subdirectory network (`localhost/wpfleet-ms/`) with seven sites covering every lifecycle state, so filters, counts and refusals all have something real to act on:

| Site | State |
|---|---|
| Fleet Test Network (blog 1) | main site |
| Marketing HQ, Company Blog, Shop | active |
| Archive | archived |
| Spammy | spam |
| Closed | deactivated (deleted flag) |

Plus three accounts: a super admin, an editor on one site, and a user with no network capabilities (used as the delegation target). Suites that need more identities create and delete their own.

Environment: XAMPP, PHP 8.0.30, MariaDB 10.4.32, WordPress 7.1.

## Suites

Twelve suites, **793 assertions**, all passing, plus an `E_ALL` pass that must come back silent. Most boot WordPress through `wp-load.php` in a Network Admin context; the guidelines and submission suites read the source alone, and the single-site suite boots a plain non-network install. Each prints `PASS`/`FAIL` per assertion and a final count.

| Suite | Assertions | Covers |
|---|---|---|
| Foundation + console + extensions + users regression | 34 | The phase 1–4 surface after later changes to shared code: discovery, status counts, console filters, inventory, operations, user directory, REST |
| Health indicators | 69 | The indicator set, states, roll-up, `unknown` handling, fleet summary, per-indicator filtering |
| Reporting | 81 | Report figures, deltas, snapshots, trends, CSV exports, printable report, REST |
| Developer indicators | 61 | Categories, performance and security indicators, thresholds, the security caveat everywhere it must appear |
| 1.0 features | 49 | Settings sanitising and filter provision, delegated access, notifications, widget, diagnostics, exports, help |
| **Multisite compatibility** | 59 | Global tables and `network_id` scoping, no per-site options, blog-switch discipline across every service, non-main-site contexts, delegation across sites, site lifecycle hooks (a probe site is created and deleted), cron placement on the main site, network-admin-only surface |
| **Permissions** | 83 | Five identities (anonymous, subscriber, site administrator, delegated viewer, super admin) against every menu entry, every screen's own re-check, all 16 REST routes, all 10 operations, the form actions and the dashboard widget |
| **Security** | 86 | Nonce enforcement per operation, 9 SQL-injection payloads across 11 query surfaces, output escaping with a hostile site and user name, CSV formula injection, secret exposure across 15 output surfaces, path traversal and target validation, capability-model tampering, settings input, plus a static scan of all 129 PHP files |
| **Performance** | 55 | Index coverage, query counts and wall time per screen at 7 and 507 sites, blog-switch counting, aggregates and exports at scale, memory, discovery time budget and resumption, retention |
| **Guidelines** | 135 | WordPress.org plugin guidelines, mechanically: headers, version agreement, readme validity, global-namespace prefixes, i18n and the shipped `.pot`, output escaping (531 echo statements read from the token stream), input handling, asset enqueuing, filesystem and network use, direct-access guards, uninstall completeness, repository hygiene |
| **Submission** | 51 | Plugin Check rules over the **built package**: no development or hidden file ships, encoding and stray-output safety, every placeholder string numbered and commented for translators, `readme.txt` parsed the way WordPress.org parses it (changelog order, stable tag, contributor slugs), review-team rules (tags, trademarks, no tracking, no server-setting changes), uninstall and lifecycle |
| **Single site** | 30 | What a reviewer does first: the plugin on a plain non-network install — it is listed correctly, activation is refused with an explanation instead of a fatal, no table, option, meta or cron event is created, and the notice and plugin-row message keep explaining the requirement |

## What the four 1.0 suites assert

### Multisite compatibility

- All four tables use `$wpdb->base_prefix` and carry `network_id`; no per-blog copy exists, and no `wpfleet_*` row appears in any site's `options` table.
- Every service — discovery, inspection, reports, user directory, extensions, snapshots, health refresh — leaves the current site and the switch stack exactly as it found them.
- From a *secondary* site: the inspector still reads the site it was asked for, repositories read the same global inventory, reports work, and the switched context is left in place.
- Delegated capabilities resolve the same on every site of the network.
- A site created with `wp_insert_site()` appears in the inventory without a discovery run, with its subdirectory path and address recorded; deleting it removes the row. (The probe site is always deleted by the ID `wp_insert_site()` returned, never by a path lookup — `get_site_by_path()` falls back to the main site, which is a genuinely dangerous footgun.)
- The discovery event exists on the network's main site only, the scheduled handler does nothing when it fires elsewhere, and the digest event matches the notification setting.

### Permissions

- The capability model: 12 capabilities, 7 delegatable, no managing capability delegatable through `grant()`, through the `wpfleet_user_capabilities` filter, or by tampering with the stored option.
- Being an administrator *of a site* grants nothing in Fleet Manager, and Multisite still reserves `update_plugins` and `manage_network_users` for super admins.
- The menu shows exactly the delegated screens; each screen also re-checks on render and dies with 403, so a direct URL is not a way in.
- Every REST route has a real permission callback (none is `__return_true`): anonymous and unprivileged users are refused by all 16, a super admin passes all 16, and a fully delegated viewer gets every read route but only discovery and the operations entry point for writes — where the pipeline then limits them to the one operation their capabilities cover.
- Permission is checked **before** the nonce: an unprivileged caller with a forged nonce is denied on capability, and the refusal is logged.
- Export and discovery form actions refuse without the capability and with a forged nonce; the diagnostics export needs `wpfleet_manage_settings` even for someone who may export reports.

### Security

- Every operation rejects a forged, empty or wrong-action nonce, and each operation has its own nonce action.
- Nine injection payloads through search, orderby, order, status, health, updates, theme, plugin, indicator, state, paging, user and log filters: no database error, no table dropped, no row added or removed, an off-list `ORDER BY` falls back to the default, and REST rejects off-list values with 400.
- A site name and a user display name set to `=2+5+cmd|' /C calc'!A0"><script>alert("xss")</script>` appear escaped on all ten screens and in the per-site and per-user detail views; the CSV exports prefix the formula with an apostrophe and still carry a UTF-8 BOM.
- Fifteen output surfaces (ten screens, two detail views, the diagnostics report, four REST payloads, the CSV exports, snapshots and the log) are searched for the admin's password hash, `DB_PASSWORD`, `AUTH_KEY`, `NONCE_SALT` and the field names `user_pass`, `user_activation_key`, `session_tokens`. None appears.
- Path traversal in plugin and theme targets (`../../wp-config.php`, null bytes, 600-character names), invalid and out-of-network site IDs, unknown users and roles are all rejected before execution; the plugin refuses to update itself and refuses to "delegate to" a super admin.
- The static scan asserts: `ABSPATH` guard in every file, `WP_UNINSTALL_PLUGIN` guard in `uninstall.php`, an `index.php` placeholder in every directory, no `eval`/`exec`/`base64_decode`/`extract`/`var_dump`/`print_r`/`error_log`/`phpinfo`, no outbound HTTP, no hard-coded credentials, no superglobal echoed directly, no query interpolating anything but a table name, and a text domain on every translation call.

### Performance

- The indexes the cost model depends on all exist, including the unique `(network_id, blog_id)` key.
- Listing 2 rows and 100 rows costs the same number of queries, and no screen switches site.
- With 500 extra inventory rows: console 6 → 6 queries, inventory 3 → 3, log 4 → 2, users 13 → 11, settings 3 → 0, health 4 → 5, reports 19 → 20, dashboard 12 → 8, diagnostics 43 → 40 — zero blog switches throughout, every screen under 0.1 s, peak memory 48 MB.
- Fleet-wide aggregates at 507 sites: health summary 2 queries, plugin usage 2, sites CSV 2, `GET /sites` 6, snapshot capture 0.05 s.
- Discovery honours its time budget: with a batch size of 2 and a zero-second budget it stops after one batch, keeps its cursor, and the next request resumes where it stopped. A completed run then reconciles the inventory, removing the 500 rows whose sites do not exist.

## Running the suites

The suites live outside the plugin (they are test harnesses, not shipped code) and expect the test network at `C:/xampp/htdocs/wpfleet-ms/`. Each is run directly:

```bash
php wf-compat.php      # Multisite compatibility
php wf-perm.php        # permissions
php wf-sec.php         # security
php wf-perf.php        # performance
php wf-guidelines.php  # WordPress.org guidelines (no WordPress needed)
php wf-single.php      # the single-site reviewer scenario
php wf-debug.php       # every path under E_ALL; must print CLEAN
php wf-build.php       # stage per .distignore and write the release ZIP
php wf-submit.php build/multisite-fleet-manager   # Plugin Check rules on the built package
```

Each prints a section-by-section `PASS`/`FAIL` list and a final count. They are **destructive by design** against the test network — they create and delete sites and users, rename a site, insert synthetic inventory rows — and each one restores what it changed; do not point them at a network you care about.

## The E_ALL pass

`wf-debug.php` installs an error handler that records only diagnostics raised **inside the plugin's own files**, then exercises 66 paths: all 17 screen states (including an out-of-range page and every filter combination), the widgets, all ten help screens, the notices, 14 REST routes, nine operations including refusals, all six CSV exports and the diagnostics report, discovery, health refresh, log purge, snapshot prune, the notification digest and the cron handler. It also listens to `deprecated_function_run`, `deprecated_argument_run`, `deprecated_hook_run`, `deprecated_class_run` and `doing_it_wrong_run`, so a deprecated core call made by the plugin is caught too.

It must print `CLEAN`. Reviewers test with `WP_DEBUG` on; a notice on screen is the kind of thing that sends a submission back.

## Known environment findings (not plugin defects)

- The test network's main site has a genuinely stuck core `do_pings` event, so its scheduled-tasks indicator legitimately reports an error. That is the indicator working.
- `wp_add_dashboard_widget()` lives in `wp-admin/includes/dashboard.php`, which is loaded by `wp-admin/network/index.php` before `wp_network_dashboard_setup` fires. A harness that calls the widget directly must require that file itself.
