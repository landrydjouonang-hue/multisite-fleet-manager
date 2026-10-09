# Contributing

Thanks for considering a contribution. This plugin manages live networks, so the bar is deliberately conservative: correctness and honesty over features.

## Getting set up

You need a **real Multisite network**. Everything that is interesting here — global tables, blog switching, super-admin-only capabilities, per-site cron — does not exist on a single site.

1. Install WordPress and enable Multisite (`WP_ALLOW_MULTISITE`, then Tools → Network Setup).
2. Clone the repository into `wp-content/plugins/multisite-fleet-manager`.
3. **Network activate** the plugin, then run site discovery once.
4. Create a few sites in different states (active, archived, spam, deactivated) and leave one with an outdated plugin. A fleet that is entirely healthy hides most of the behaviour.

Dev tooling is optional and never needed at runtime:

```bash
composer install
composer lint        # PHPCS: WordPress standards + PHP 8.0 compatibility
composer lint:fix
composer lint:php    # php -l on every file
```

## Ground rules

**PHP 8.0 is the floor.** No enums, readonly properties, `never`, or 8.1+ syntax. `multisite-fleet-manager.php` must additionally stay parseable by much older PHP, so the version notice works instead of a fatal error.

**No runtime dependencies.** No Composer autoloader in production, no bundled libraries, no build step. The plugin must work from a plain ZIP.

**No outbound HTTP.** No telemetry, no phone-home, no remote API. Update data comes from what WordPress already stores.

**Every state change goes through `OperationService`.** Capability (the plugin's *and* core's) → per-operation nonce → target validation → network lock → execute → audit log. Handlers must not check permissions or nonces themselves; that keeps the security rules in one place. If you need a new kind of change, add a handler, not a new path.

**No `switch_to_blog()` on a read path.** List screens, summaries, reports and exports read the stored inventory. Only `SiteInspector` and the per-site operation handlers may switch, and they restore in `finally`. Watch out for core helpers that switch internally (`get_admin_url( $blog_id )`, `get_site_url( $blog_id )`, `get_blogaddress_by_id()`): use the stored address instead.

**Escape at output, sanitise and validate at input.** `esc_html()`, `esc_attr()`, `esc_url()` at the point of echo; whitelists for anything that reaches SQL (`ORDER BY`, statuses, filters); `$wpdb->prepare()` everywhere; `esc_like()` for `LIKE`.

**Never expose authentication data.** Passwords, hashes, activation keys, session tokens and application passwords must not reach the interface, the REST API, exports, the log or the diagnostics report. E-mail addresses are for super admins only.

**Say what the plugin cannot do.** Anything unmeasurable reports as *unknown*, never as a problem and never as a pass. The security indicators must keep stating that they are configuration signals, not a security assessment. Please do not add wording that implies malware, vulnerability or intrusion detection.

**Internationalise everything** with the `multisite-fleet-manager` text domain, including strings in templates, with `/* translators: */` comments where the placeholders need explaining.

## Code shape

- Namespace `FleetManager`, PSR-4 from `src/`; one class per file, file named after the class (the autoloader depends on it).
- Classes do not emit markup; templates in `templates/admin/` receive a prepared `$vars` array and do not query.
- WordPress coding standards, tabs for indentation, Yoda conditions, full docblocks with `@param`/`@return`.
- Prefixes: `wpfleet_` for options, hooks, capabilities, cron hooks and tables; `WPFLEET_` for constants; `wpfc-`/`wpfx-`/`wpfd-` for CSS classes.
- JavaScript is progressive enhancement. Every screen, filter and action must work with JavaScript disabled.

## Database changes

Bump `Storage\Schema::VERSION`, add the column or table to the `dbDelta()` definition, and make sure `Core\Upgrader` handles the upgrade without losing rows. Document the column and the upgrade step in `docs/DATABASE.md`, and say what the data looks like *before* the next discovery run fills it.

## Testing

There is no unit-test suite; the plugin is tested with scripted integration suites against a real network (see [docs/TESTING.md](docs/TESTING.md)) covering features, Multisite compatibility, permissions, security and performance. For a pull request, please at least:

- exercise the change on a network with several sites in different states, network activated;
- try it as a non-super-admin and as a delegated user, not only as a super admin;
- check the Operation Log afterwards if your change touches an operation;
- for anything on a screen or in a query, report the query count before and after (the performance suite exists because a single per-row core call cost 100+ queries on a 500-site fleet).

## Commits and pull requests

- Present tense, imperative, explaining the *why*: "Build row links from the stored address to avoid a site switch per row".
- One concern per pull request.
- Update `CHANGELOG.md` under a new or the unreleased heading.
- Fill in the pull-request checklist honestly — "not tested on a large network" is useful information, not a failure.

## Security

Please do not open a public issue for a vulnerability. See [SECURITY.md](SECURITY.md).

## License

Contributions are accepted under the plugin's license, GPL-2.0-or-later.
