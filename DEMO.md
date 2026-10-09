# Demo and portfolio notes

A ten-minute walkthrough of Multisite Fleet Manager, in the order that shows the product best, with the engineering decision behind each screen. Written to be followed live or read on its own.

## Setting up a demo network

Any Multisite network works, but a demo lands better when the sites are not all healthy. Seven sites are enough:

| Site | Make it | Shows |
|---|---|---|
| Main site | — | "Main site" tag, network-wide figures |
| Two or three active sites | one with an outdated plugin, one with an old theme | update counts, Needs Attention |
| One with a deleted plugin folder | delete the folder of an active plugin | a real **Error**: "Plugin files missing" |
| One archived | Network Admin → Sites → Archive | lifecycle status, excluded from operations |
| One spam, one deactivated | same screen | refusals with a reason |

Then: **Network Admin → Fleet Manager → Discover sites**. Everything below reads what that one pass stored.

## The walkthrough

**1. Activate it on a single site first.** Thirty seconds, and it sets the tone: activation is refused with an explanation of what Multisite is and how to enable it. The plugin never pretends to be on a network. (`Core\MultisiteGuard`, `Admin\RequirementNotice`.)

**2. Dashboard.** Status counts, network facts, theme usage, recent registrations and updates. Point out that this is one inventory read, not a walk over the network.

**3. Site Dashboard — the centrepiece.** One row per site: status, health, WordPress version, active theme, plugin and theme updates. Then:

- click a KPI tile — *Needs Attention* — and watch it become a filter;
- sort by Health: errors first, which is the default order because that is what you want to see;
- expand a row: every indicator with a plain-English explanation of what it means and what to do;
- copy the URL and paste it in another tab — every view is a plain GET URL, bookmarkable, and works with JavaScript off;
- shrink the window: below 782 px the table becomes stacked cards.

**4. Health.** The 21 indicators grouped as Reliability, Security, Performance, Updates, each with fleet-wide counts that link to exactly those sites. Read the security caveat out loud — it is on the screen, not in a footnote:

> These are configuration indicators, not a security assessment. No malware, file-change, vulnerability, password or intrusion detection. A clean result is not evidence that a site is safe.

That sentence is a feature. Say what a tool does *not* do and people can trust what it says it does.

**5. Plugins & Themes.** What is installed, where it runs, what can be updated. Update one plugin from the row button, then switch to the **Operation Log** and show the entry: who, what, which versions, which source. Then try the same update as a user without the capability — the refusal is logged too.

Worth saying: plugin files are shared by the whole network, so an update always applies to every site; the screen says so rather than pretending updates are per-site.

**6. Users.** Every account, its roles, its sites, its last activity — and the honest labelling of where "last activity" comes from ("last sign-in", "from an open session", "not seen yet"), because WordPress does not record a last-login date. Add a user to a site, change their role, then try to remove the last administrator of a site: refused, with the reason.

**7. Reports.** Headline figures with the change since the last snapshot, the indicators failing on the most sites, trends, pending updates. Export a CSV and open it in a spreadsheet; mention that a site named `=cmd|…` cannot execute, because every exported value starting with `=`, `+`, `-` or `@` is prefixed. Open the printable report and print to PDF — no PDF library is bundled, because the browser already does it well.

**8. Settings.** Discovery schedule and batch size, retention, notifications, thresholds, delegated access. Then the decision worth explaining: the screen does not store a second source of truth — it *provides* the plugin's own filters at priority 10, so a developer's `add_filter( …, 20 )` always wins.

**9. Delegated access.** Grant *view sites* and *view reports* to a non-super-admin, log in as them: they see three screens, no settings, no actions. Then show that `wpfleet_manage_updates` is not even offered — the managing capabilities cannot be delegated, because they change files for the whole network.

**10. Diagnostics.** Environment, self-checks, every REST route with its capability, every hook with its description, and a copy-paste report with no site names, addresses or secrets in it — made to be pasted into a public bug report.

## What to say about the engineering

Four points carry the whole product:

**Inventory, not live polling.** Discovery reads each site once, in resumable batches with a time budget and a keyset cursor (so creating or deleting sites mid-run cannot skip or repeat one). Screens read that inventory. On a 500-site network the console costs **6 queries and zero `switch_to_blog()` calls**; the fleet health summary costs 2. The performance suite measures this at 7 and 507 sites and fails if a screen starts scaling with the fleet — which is how a per-row `get_admin_url()` call (one site switch and one query per row) was caught before release.

**One pipeline for every state change.** REST, form posts and bulk actions all go through `OperationService`: capability (the plugin's *and* core's), per-operation nonce, target validation, network lock, execute, log. Handlers never check permissions, so the rules live in exactly one place — and every outcome is auditable, including refusals and operations killed by a fatal error.

**Honest unknowns.** `disk_free_space()` is disabled on many hosts; upload quotas are often not enforced; WordPress may not have checked for updates yet. Each of those reports *unknown* and never counts against a site's health. A dashboard that invents numbers is worse than one that admits what it cannot see.

**Multisite as the premise, not a mode.** Global tables keyed by `network_id` (so multi-network installs stay isolated), usermeta read directly because it is global, cron on the network's main site because cron is per site, and no screen outside Network Admin. The compatibility suite checks the switch stack after every service call.

## Screenshots to capture

For the WordPress.org listing and the repository, 1200×900 or wider, with a fleet that has a mix of health states:

1. `site-dashboard.png` — the console with a filter applied and one row expanded
2. `dashboard.png`
3. `health.png` — the grouped indicators, security group visible with its caveat
4. `plugins-themes.png`
5. `users.png`
6. `reports.png`
7. `operation-log.png` — including a refused entry
8. `settings.png` — delegated access visible

Put them in `.wordpress-org/` as `screenshot-1.png` … `screenshot-8.png`, matching the order in `readme.txt`.
