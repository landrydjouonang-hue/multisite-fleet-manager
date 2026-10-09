**What this changes**

<!-- One or two sentences. Link the issue if there is one. -->

**Why**

<!-- The problem it solves. -->

**Checklist**

- [ ] Tested on a real Multisite network, network activated
- [ ] `composer lint` passes (WordPress coding standards, PHP 8.0+ compatible)
- [ ] Every state change still goes through `OperationService` (capability → nonce → target → lock → log)
- [ ] Output is escaped at the point of output; input is sanitised and validated
- [ ] No `switch_to_blog()` added to a list, summary, report or export path
- [ ] New strings are translatable with the `multisite-fleet-manager` text domain
- [ ] New capability, hook, option or column documented in `README.md` / `docs/`
- [ ] `CHANGELOG.md` updated
- [ ] No new runtime dependency and no outbound HTTP request

**Anything measured**

<!-- For changes that touch a screen or a query: query count and time before/after, ideally on a large inventory. -->
