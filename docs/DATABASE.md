# Database

All datetimes are UTC.

## Table `{base_prefix}wpfleet_sites`

The table is global, like `wp_blogs`: one table for the whole installation, keyed by `network_id`. It is created on network activation with `dbDelta`, and re-run by `Core\Upgrader` when `Schema::VERSION` changes.

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned PK AI | |
| network_id, blog_id | bigint unsigned | UNIQUE (network_id, blog_id) |
| domain, path | varchar(200), varchar(100) | From `wp_blogs` |
| name, site_url, home_url, admin_email | varchar | From the site's options |
| status | varchar(20) | Derived: `deleted` > `spam` > `archived` > `active` |
| is_main, is_public, is_archived, is_mature, is_spam, is_deleted | tinyint(1) | Raw core flags |
| locale | varchar(20) | `WPLANG`, falling back to the network value, then `en_US` |
| theme_stylesheet, theme_name | varchar | Active theme |
| active_plugins | smallint | Site-level activations (network-activated plugins are counted separately) |
| user_count, post_count, page_count | int | Members; published posts and pages |
| db_version | int | The site's `db_version` option |
| registered_at, last_updated_at | datetime NULL | From `wp_blogs`. `0000-00-00` is stored as NULL |
| discovered_at | datetime | First seen; kept on update |
| synced_at | datetime | Last refresh |
| sync_run | varchar(36) | Discovery run that last saw the row (`''` for real-time updates) |
| theme_template | varchar(191) | Parent theme (equals the stylesheet for non-child themes). *Schema 2* |
| active_plugin_list | longtext NULL | JSON list of site-level active plugin basenames. *Schema 2* |
| plugin_updates, theme_updates | smallint NULL | Available updates; NULL when WordPress has no update data yet. *Schema 2* |
| health | varchar(20) | `healthy`, `attention`, `error`; `''` = not checked yet. *Schema 2* |
| health_indicators | longtext NULL | JSON list of `{id, state, data}`; messages are generated at display time. *Schema 2* |
| health_checked_at | datetime NULL | Last evaluation. *Schema 2* |
| cron_events, cron_overdue | smallint | Scheduled events on the site, and how many are overdue. *Schema 5* |
| cron_late_seconds | int | How late the oldest overdue event is. *Schema 5* |
| autoload_bytes | bigint NULL | Size of the site's autoloaded options; NULL when unreadable. *Schema 5* |
| db_size_bytes | bigint NULL | Size of the site's tables; NULL when `information_schema` is not readable. *Schema 5* |
| upload_used_bytes, upload_quota_bytes | bigint NULL | Upload space used and allowed; NULL when the network enforces no quota. *Schema 5* |
| admin_count | smallint | Users with the administrator role on the site. *Schema 7* |

Indexes: `network_status`, `network_health`, `network_registered`, `network_updated`.

### Upgrade 1 → 2

`Core\Upgrader` runs `dbDelta`, which adds the columns and index while keeping existing rows. Rows then show as "not checked". Because only a discovery run can collect the plugin list and parent theme, the upgrader queues one right away (`wpfleet_continue_discovery`).

All queries use `$wpdb->prepare()`. `ORDER BY` values come from a whitelist (`SiteRepository::ORDERBY`), and `LIKE` input is escaped with `esc_like()`.

## Table `{base_prefix}wpfleet_operations` (schema 3)

Append-only audit log of every state-changing operation, global like the sites table and keyed by `network_id`.

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned PK AI | |
| network_id, user_id | bigint unsigned | Who ran it, on which network |
| operation | varchar(40) | `plugin_update`, `theme_update`, `plugin_activate`, `plugin_deactivate` |
| target_type, target, target_name | varchar | `plugin`/`theme`, the basename or stylesheet, and the display name at the time |
| site_id | bigint unsigned | Target site; **0 = network-wide** (updates) |
| status | varchar(20) | `success`, `failed`, `rejected` (bad nonce, invalid target, busy, unsupported), `denied` (missing capability) |
| from_version, to_version | varchar(50) | Version before and after |
| message | text | Human-readable outcome |
| context | longtext | JSON: `source` (rest/form), `error` code, extra details |
| created_at | datetime | UTC |

Indexes: `network_created`, `network_status`, `network_operation`, `network_site`.

Entries are never updated or deleted by the plugin except by retention: `OperationLog::purge()` runs on the daily discovery event and deletes entries older than 180 days (`wpfleet_operation_log_retention_days`, minimum 7).

### Upgrade 2 → 3

`dbDelta` creates the new table. Nothing else changes, so no rediscovery is needed.

## Table `{base_prefix}wpfleet_user_sites` (schema 4)

Queryable index of "which user has which role on which site". Roles live in per-site `usermeta` keys, which cannot be filtered or counted network-wide; this table makes that possible. `usermeta` stays the source of truth. **No credentials are stored.**

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned PK AI | |
| network_id, site_id, user_id | bigint unsigned | UNIQUE (network_id, site_id, user_id) |
| roles | varchar(255) | Comma-separated role slugs; empty means a membership with no role |
| updated_at | datetime | Also used to drop rows a re-index did not see |

Indexes: `network_user`, `network_site`, `network_roles`.

Filled by discovery (one query per site, no site switching) and kept current by core's hooks: `add_user_to_blog`, `remove_user_from_blog`, `set_user_role`, `add_user_role`, `remove_user_role`, `wpmu_delete_user`, `wp_delete_site`. Sites with more than `wpfleet_user_index_limit` members (default 5,000) are skipped and listed in the `wpfleet_user_index_skipped` option.

### Upgrade 6 → 7

`dbDelta` adds `admin_count`. It fills on the next discovery run; until then the administrators indicator reports every site as having none, so run discovery after upgrading.

### Upgrade 4 → 5

`dbDelta` adds the cron, autoload, database-size and upload columns. They fill on the next discovery run; until then those indicators report as unknown.

### Upgrade 3 → 4

`dbDelta` creates the table. It fills on the next discovery run; until then the Users screen says memberships are not available yet.

## Table `{base_prefix}wpfleet_snapshots` (schema 6)

One row per captured report, per network.

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned PK AI | |
| network_id | bigint unsigned | |
| captured_at | datetime | UTC |
| source | varchar(20) | `scheduled` (after discovery) or `manual` |
| user_id | bigint unsigned | Who asked, for manual captures |
| sites, healthy, attention, error, unchecked | int | Headline figures as columns, so trends are a plain query |
| sites_needing_updates, plugin_updates, theme_updates | int | |
| data | longtext | The whole report summary as JSON |

Index: `network_captured`.

Captured after discovery at most once per `wpfleet_snapshot_interval` (default 12 hours, 0 captures every time), or on request through the `snapshot_capture` operation. Pruned after `wpfleet_snapshot_retention_days` (default 365) on the daily event.

### Upgrade 5 → 6

`dbDelta` creates the table. The first snapshot is captured on the next discovery run; trends need two.

## User meta

| Key | Content |
|---|---|
| `wpfleet_last_login` | Timestamp of the last successful login seen by Fleet Manager |
| `wpfleet_last_login_site` | Blog ID where that login happened |

Both are removed on uninstall. Session tokens are read for open-session counts and login times only, and never stored or exposed.

## Network options (`wp_sitemeta`)

| Key | Content |
|---|---|
| `wpfleet_db_version` | Schema version (main network) |
| `wpfleet_discovery_run` | State of the run in progress (removed on completion or deactivation) |
| `wpfleet_last_discovery` | Summary of the last completed run |
| `wpfleet_access` | Capability grants: `user_id => string[]` |
| `wpfleet_settings` | Network settings; always the exact shape of `Settings::defaults()` |
| `wpfleet_notification_state` | Last digest: when it was sent and the outcome (`sent`, `skipped`, `no_recipients`) |
| `wpfleet_activated` | One-time activation notice flag |
| `wpfleet_user_index_skipped` | Sites whose memberships were too large to index |
| `wpfleet_operation_lock` (site transient) | Held while an operation runs (10 minutes max) |
| `wpfleet_results_{user}` (site transient) | Results of a no-JavaScript form run, shown once (5 minutes) |

### Settings

`wpfleet_settings` is written only through `Settings::save()`, which runs `sanitize()` first, so the stored array always has exactly the keys of `Settings::defaults()`:

| Key | Default | Bounds |
|---|---|---|
| `discovery_recurrence` | `daily` | a registered cron schedule |
| `discovery_batch_size` | 25 | 5–200 |
| `log_retention_days` | 180 | 7–3650 |
| `snapshot_retention_days` | 365 | 7–3650 |
| `snapshot_interval_hours` | 12 | 0–720 (0 = every discovery run) |
| `notifications_enabled` | false | boolean |
| `notification_recipients` | `''` | comma/space separated, valid addresses only |
| `notification_frequency` | `weekly` | `daily` or `weekly` |
| `notify_errors`, `notify_updates`, `notify_failed_ops` | true | boolean |
| `recommended_php` | `8.1` | version-shaped string |
| `many_administrators` | 5 | 2–100 |
| `autoload_warning_kb` | 800 | 50–102400 |
| `database_warning_mb` | 512 | 10–1048576 |
| `active_plugin_warning` | 25 | 5–500 |
| `memory_warning_mb` | 128 | 32–4096 |

Each value is applied by hooking the matching filter at priority 10, so a filter in code at a later priority overrides the screen. Saving runs through `OperationService`, and the audit entry records which keys changed — never their values.

## Cron (on each network's main site)

- `wpfleet_scheduled_discovery`: recurring, `discovery_recurrence` (daily by default). Log purging and snapshot pruning ride on this event.
- `wpfleet_continue_discovery`: one-off; the first run after activation, or resuming a run that ran out of time.
- `wpfleet_send_digest`: recurring, `notification_frequency`; scheduled only while notifications are enabled, and rescheduled when the setting changes.

## Lifecycle

- **Deactivation**: clears the cron events and any run in progress. The inventory and the audit log are kept, so reactivating does not lose history.
- **Uninstall**: drops the four tables, deletes the options above on every network, removes the `wpfleet_last_login*` user meta, and clears the cron events.
