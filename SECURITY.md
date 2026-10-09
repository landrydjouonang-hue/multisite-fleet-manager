# Security policy

## Supported versions

| Version | Supported |
|---|---|
| 1.0.x | yes |
| < 1.0 | no — pre-release phases, please upgrade |

## Reporting a vulnerability

Please report privately, not as a public issue:

- open a [private security advisory](https://github.com/landrydjouonang-hue/multisite-fleet-manager/security/advisories/new) on GitHub, or
- e-mail the author with "Multisite Fleet Manager security" in the subject.

Helpful to include: the version, whether the network is subdomain or subdirectory, the role of the account involved (super admin, delegated user, site administrator, subscriber, anonymous), the request or screen, and what an attacker gains. A diagnostics report (Fleet Manager → Diagnostics → Download report) contains no names or secrets and is safe to attach.

You will get an acknowledgement within a few days, an assessment with a fix plan, and credit in the changelog when the fix ships — unless you would rather not be named. Please give a reasonable window before disclosing publicly.

## What counts as a vulnerability here

In scope:

- reaching any screen, REST route or operation without the capability it requires;
- a state change that bypasses the pipeline (capability → nonce → target validation → audit log);
- SQL injection, stored or reflected XSS, CSV formula injection in an export;
- exposure of authentication data (passwords, hashes, activation keys, session tokens, application passwords) or of an e-mail address to someone who is not a super admin;
- a delegated (read-only) user gaining a managing capability, or a site administrator gaining anything network-wide;
- path traversal through a plugin, theme or site target;
- the diagnostics report or an export leaking site names, addresses, e-mail addresses or secrets.

Out of scope:

- anything that requires super-admin access to exploit: a super admin can already install code, so "a super admin can change the thresholds" is a feature, not a finding;
- the plugin not detecting malware, modified files, vulnerable versions, weak passwords or intrusions. It states on every relevant screen that it is not a security scanner — missing detection is the documented scope, not a vulnerability;
- findings in WordPress core, other plugins or the host, unless this plugin makes them exploitable;
- reports from automated scanners with no demonstrated impact.

## How the plugin is built to limit damage

- Every state change passes one pipeline: capability check (the plugin's own *and* core's, which Multisite reserves for super admins), a per-operation nonce, target validation against *this* network, a network-wide lock, then an audit entry whatever the outcome — including refusals.
- The capabilities that change files, users or settings cannot be delegated to non-super-admins, by design and enforced in code (grants are intersected with the delegatable set on write *and* on read).
- Authentication data is never read into the interface, the REST API, exports, the log or the diagnostics report.
- No outbound HTTP requests, no telemetry and no third-party services, so there is nowhere for data to go.
- Reads are prepared statements with whitelisted `ORDER BY` and filter values; exports neutralise spreadsheet formulas; output is escaped at the point of output.
- A security suite of 86 assertions exercises these claims on every release: nonce enforcement per operation, injection payloads across every query surface, hostile site and user names rendered on every screen, secret exposure across every output surface, path traversal, capability tampering, and a static scan of the source. See [docs/TESTING.md](docs/TESTING.md).
