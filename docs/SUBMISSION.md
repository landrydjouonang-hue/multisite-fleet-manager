# Submitting to the WordPress.org plugin directory

Everything here is about the **first** submission of version 1.0.0. The package is built and audited; what remains is three things only you can do (marked **you**) and the upload itself.

## 1. State of play

| Item | Status |
|---|---|
| Package `multisite-fleet-manager-1.0.0.zip` | built, 251 KB, 146 files, one top-level folder |
| Slug `multisite-fleet-manager` | checked as free on wordpress.org before the rename |
| `readme.txt` | parses as WordPress.org parses it; all required headers, changelog newest-first, upgrade notice, 8 screenshot captions |
| Plugin header | name, description, version, requires-at-least, requires-PHP, author, GPL, text domain, domain path, `Network: true` |
| Version agreement | header `1.0.0` = `WPFLEET_VERSION` = readme `Stable tag` |
| Translation template | `languages/multisite-fleet-manager.pot`, 807 strings |
| Guidelines audit | 135 checks |
| Submission audit (Plugin-Check rules) | 51 checks on the built package |
| Single-site reviewer scenario | 30 checks: refusal is explained, nothing written, no fatal |
| `E_ALL` pass | 66 paths exercised, zero notices, warnings or deprecations from plugin code |
| Feature, compatibility, permission, security, performance suites | 712 checks |
| WordPress.org username | `Contributors: landrydjouonang`, confirmed by the author |
| GitHub repository | `github.com/landrydjouonang-hue/multisite-fleet-manager`, confirmed by the author (used in `composer.json`, `SECURITY.md`, the issue-template link and the `.pot` header) |
| **Plugin Check plugin** | **you** — run it once locally (below); it needs network access to install |
| **Listing assets** (icon, banner, screenshots) | **you** — not needed to submit; added after approval |

## 2. Run Plugin Check once (recommended, not required)

The review team runs [Plugin Check](https://wordpress.org/plugins/plugin-check/). It overlaps heavily with the audits already run here, but it is the exact tool they use, so one local run removes all doubt. It needs internet access to install, which this machine does not have.

On any single-site WordPress with internet:

1. Install and activate **Plugin Check**.
2. Drop `multisite-fleet-manager` into `wp-content/plugins/` (do not activate it — Plugin Check reads files; activating it on a single site is refused by design).
3. Tools → Plugin Check → select Multisite Fleet Manager → run all categories.

Expect zero errors. Two warnings are possible and are both fine to leave:

- *`load_plugin_textdomain()` is not required since WordPress 4.6* — kept deliberately so bundled translations also work outside the directory.
- *Found `switch_to_blog()`* — if flagged at all, it appears only in `SiteInspector` and the per-site operation handlers, each balanced by `restore_current_blog()` in a `finally`. That is the documented, intended use on Multisite.

If it reports anything else, send me the output.

## 3. Submit

1. Sign in at **https://wordpress.org/plugins/developers/add/**.
2. Upload `multisite-fleet-manager-1.0.0.zip`.
3. Paste the reviewer notes below into the notes field.

Then wait. First review is typically a few days to a few weeks. Replies come by e-mail from `plugins@wordpress.org` — answer in the same thread.

### Reviewer notes to paste

> **Multisite Fleet Manager is a network-only plugin (`Network: true`) and requires WordPress Multisite.**
>
> If you activate it on a single-site installation, activation is deliberately refused with an explanation of what Multisite is and how to enable it; nothing is written to the database and no tables are created. That refusal is the intended behaviour, not a failure. On a network, it must be **network activated**; activated on one site only, it stays dormant and asks for network activation. It never emulates a network.
>
> To review it fully, please use a Multisite network with two or more sites, network activate the plugin, then open **Network Admin → Fleet Manager** and click **Discover sites** once. Discovery is read-only; it reads each site's options and counts and writes nothing to the sites.
>
> Notes that may save you time:
>
> - **No external requests.** The plugin makes no HTTP calls of any kind, has no telemetry, no analytics and no third-party services. Update information comes from the data WordPress already stores (`update_plugins` / `update_themes` site transients).
> - **No runtime dependencies**, no build step, no bundled libraries. `composer.json` (excluded from the package) is development tooling only: PHPCS with WordPress standards.
> - **Every state change** goes through one pipeline in `src/Operations/OperationService.php`: capability check (the plugin's own capability *and* core's, which Multisite reserves for super admins) → per-operation nonce → target validation → network lock → audit log entry, whatever the outcome. Handlers never check permissions themselves.
> - **Capabilities.** Seven read-only capabilities can be delegated to non-super-admins; the five that change files, users or settings cannot be delegated, enforced on both write and read.
> - **No authentication data is ever read into the UI, the REST API, exports or the log** — no passwords, hashes, activation keys, session tokens or application passwords. E-mail addresses are shown to super administrators only. The downloadable diagnostics report contains no site names, addresses or e-mail addresses.
> - **Tables.** Four installation-global tables (`{base_prefix}wpfleet_sites`, `_operations`, `_user_sites`, `_snapshots`), created with `dbDelta()` and keyed by `network_id` so multi-network installs stay isolated. `uninstall.php` drops them, removes the plugin's network options on every network, deletes its user meta and clears its cron events.
> - **Scope statement.** The health indicators are operational (versions, updates, configuration, capacity). The plugin states on every relevant screen, in the REST payload and in the readme that it is **not** a security scanner and performs no malware, file-integrity, vulnerability or intrusion detection. Please hold us to that wording rather than asking for scanning claims.
> - Author: Djouonang Landry. Licensed GPL-2.0-or-later; `LICENSE` ships with the package.

## 4. After approval

You get SVN access at `https://plugins.svn.wordpress.org/multisite-fleet-manager/`, laid out as:

```
/trunk     the current code
/tags/1.0.0   the released version (what users install, per readme.txt "Stable tag")
/assets    icon, banner and screenshots (NOT in the plugin ZIP)
```

```bash
svn co https://plugins.svn.wordpress.org/multisite-fleet-manager/ mfm-svn
cd mfm-svn
# copy the contents of the built package into trunk/, then:
svn cp trunk tags/1.0.0
svn ci -m "Release 1.0.0"
```

`Stable tag: 1.0.0` in `readme.txt` means users receive `/tags/1.0.0`, so tag every release and keep the stable tag in step.

### Assets to produce (`/assets`, from `.wordpress-org/`)

| File | Size |
|---|---|
| `icon-128x128.png`, `icon-256x256.png` | icon — export from `assets-source/icon.svg`; PNG only, SVG is rejected for these |
| `banner-772x250.png`, `banner-1544x500.png` | header banner |
| `screenshot-1.png` … `screenshot-8.png` | 1200×900 or wider, in the order of the `== Screenshots ==` captions in `readme.txt` |

`docs/DEMO.md` says which eight screens to capture and how to set up a network that shows a mix of health states. Blur real site names and e-mail addresses.

## 5. Rebuilding the package

The package is whatever `.distignore` leaves behind, zipped with a single top-level folder named after the slug. With wp-cli:

```bash
wp dist-archive . ../multisite-fleet-manager-1.0.0.zip
```

Excluded from the package (23 files): `docs/`, `.github/`, `.wordpress-org/`, `assets-source/`, `README.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, `composer.json`, `phpcs.xml.dist`, `.gitignore`, `.distignore`, `.editorconfig`. Everything under `src/`, `templates/`, `assets/` and `languages/` ships, plus `readme.txt`, `LICENSE`, `uninstall.php`, `index.php` and the main file.

Before uploading a rebuilt package, re-run the audits (`docs/TESTING.md` lists them) — the submission audit accepts a directory argument so it can check the staged package rather than the working copy.

## 6. If the review comes back with changes

Common requests and where the answer already is:

| Request | Answer |
|---|---|
| "Sanitize/escape/nonce X" | Point at `OperationService` for state changes and the screen's own `current_user_can()` + `wp_die()`; the security suite covers 86 assertions on exactly this |
| "Remove the `wordpress` tag / trademark" | Already absent from the name, slug and tags |
| "Declare external services" | There are none; say so plainly |
| "Don't call `load_plugin_textdomain()`" | Harmless to remove if they insist — delete the `load_textdomain()` method and its `init` hook in `src/Plugin.php` |
| "Prefix everything" | Prefixes are `wpfleet_` / `WPFLEET_` / namespace `FleetManager`; the guidelines audit asserts no unprefixed global |
| "Your plugin does nothing on activation" | It is Multisite-only; see the reviewer notes above |

Bump the version in **three** places for any resubmission — the plugin header, `WPFLEET_VERSION` and `readme.txt`'s `Stable tag` — plus a `CHANGELOG.md` and `readme.txt` changelog entry. The CI workflow and the submission audit both fail if those disagree.
