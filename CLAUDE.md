# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

McGreen Acres is a Drupal 11 e-commerce site for a farm/herd share business. It uses Drupal Commerce with recurring subscriptions (herd shares) and a custom theme. Stripe is the only live payment gateway (`stripe_card`, `stripe_payment_element`); the `square`, `check`, `cash`, and `zelle` gateways use Commerce's `manual` plugin so staff can record offline payments in the order system — keeping every income source trackable at an administrative level (reporting, revenue charts). The local dev environment is managed via Lando.

## Common Commands

All `drush` and `composer` commands must be run via Lando:

```bash
lando drush <command>        # Drush commands (--uri is set from the Lando app name; see .lando/scripts/drush.sh)
lando composer <command>     # Composer commands
lando build                  # Full rebuild: composer install → drop local db → sql-sync from @prod → post-sync drush tasks via fourkitchens/pots
```

Useful drush commands:

```bash
lando drush cr               # Clear cache
lando drush cim -y           # Import config from config/sync/
lando drush cex -y           # Export config to config/sync/
lando drush updb -y          # Run database updates
lando drush sql-sync @prod @self -y  # Pull prod database locally
```

PHP linting (no build tools — theme CSS/JS is plain, no compilation):

```bash
# From composer.json "lint" script:
find web/modules/custom web/themes/custom \( -iname '*.php' -o -iname '*.inc' -o -iname '*.module' -o -iname '*.install' -o -iname '*.theme' \) '!' -path '*/node_modules/*' -print0 | xargs -0 -n1 -P8 php -l
```

### Running PHPUnit tests

`lando phpunit <path>` runs PHPUnit via `.lando/scripts/phpunit.sh` (e.g. `lando phpunit web/modules/contrib/simplenews/tests/src/Functional/SomeTest.php --filter testFoo`). Two gotchas:

- **`web/core/tests` is stripped by `drupal-core-vendor-hardening`** (see `composer.json` `extra.drupal-core-vendor-hardening`) to keep hosting inodes down. This directory contains `bootstrap.php`, so PHPUnit cannot run at all without it. Before testing, temporarily remove `"tests"` from that array, run `lando composer reinstall drupal/core --no-progress` to restore it, run tests, then put `"tests"` back and reinstall again to re-strip it. Don't leave it un-hardened. The same hardening block also strips `drupal/commerce_stripe` tests and the `stripe/stripe` README.
- **`SIMPLETEST_BASE_URL` must be `https://localhost`, not the `.lndo.site` domain.** Using the proxied `lndo.site` URL causes an HTTP→HTTPS redirect mid-test (e.g. during one-time-login links), which desyncs Drupal's `SESS`/`SSESS` session cookie naming and makes `drupalLogin()` assertions fail even though the login actually succeeded. `https://localhost` hits the container's Apache directly and avoids the redirect. This is already configured in `.lando/scripts/phpunit.sh` — don't change it without re-testing.

### Driving the local site in a browser (Playwright)

The Lando certificate for the local site (`https://mcgreen-acres.lndo.site`, or `https://<app-name>.lndo.site` for a checkout with its own `.lando.local.yml` `name:` — see "Running multiple checkouts side by side") is not trusted by the Playwright browser, so a plain `page.goto()` fails with `net::ERR_CERT_AUTHORITY_INVALID` and nothing else works. Turn certificate errors off by opening the page in a new browser context. With the `browser_run_code_unsafe` tool:

```js
async (page) => {
  const ctx = await page.context().browser().newContext({ ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  await p.goto('https://mcgreen-acres.lndo.site/some/path', { waitUntil: 'networkidle' });
  // ...interact, screenshot, read values...
  await ctx.close();
}
```

Use that context's page for everything (the tool's own `page` can't be changed after it is created). For a quick HTTP check use `curl -sk` (`-k` skips the certificate check). Scope form locators to the form (e.g. `form.webform-submission-pig-cut-sheet-add-form`), because the site's other forms, such as the newsletter signup, also have a submit button.

## Architecture

### Hosting & Deployment

- **Local**: Lando with MariaDB 11.4, PHP 8.5, mailpit for email capture. Site URL: `https://mcgreen-acres.lndo.site` Mailpit: https://mail-mcgreen-acres.lndo.site (these are the defaults for the main checkout; other checkouts use their own app name — see below)
- **Running multiple checkouts side by side**: each checkout of this repo can run as its own Lando app at the same time, provided its gitignored `.lando.local.yml` sets a unique `name:`, a unique mailpit proxy hostname and a unique database `portforward`. Lando derives the site URL (`https://<name>.lndo.site`), containers, network and database volume from `name`, so nothing else is shared. `.lando.yml` therefore must not hard-code a site hostname or port: `lando drush` / `lando build` take the URI from `$LANDO_APP_NAME` via `.lando/scripts/drush.sh`, and `.lando.yml` has no `proxy:` block because Lando *concatenates* proxy lists from the two files, so a mail hostname set in `.lando.yml` would be claimed by every checkout and collide. Minimal `.lando.local.yml` for a second checkout (the main checkout needs the same, with its own values, e.g. `name: mcgreen-acres` is the default and `mail-mcgreen-acres.lndo.site` for mail):

  ```yaml
  name: mcgreen-acres-agent2          # unique per checkout → https://mcgreen-acres-agent2.lndo.site
  proxy:
    mail:
      - mail-mcgreen-acres-agent2.lndo.site   # unique; Mailpit UI
  services:
    database:
      portforward: 60200              # unique host port (main checkout uses 60100)
  ```

  After changing `.lando.local.yml`, run `lando rebuild -y` (or `lando restart` for proxy-only changes). Each checkout has its own database, so run `lando build` (or `lando db-import`) in a new one to populate it. `settings.php` treats the site as `local` unless a `live.txt` exists, and opens trusted hosts when local, so no Drupal settings change is needed; just use the checkout's own URL in browsers, Playwright and curl (e.g. `https://mcgreen-acres-agent2.lndo.site`). `lando phpunit` is unaffected (it uses `https://localhost` inside the container).
- **Production**: Shared hosting at `mcgreenacres.com` — connection details are in `drush/sites/self.site.yml` (not committed)
- **CI/CD** (`.circleci/config.yml`): pushing to `master` is safe on its own — it only runs `static_tests` + `build`, then a `deploy` job that auto-builds an artifact (strips `.git` dirs/tests) and commits/pushes it to the `deploy-master` branch. Nothing reaches production yet at this point. That job (`.circleci/scripts/deploy`) also tags the source commit on `master` with a date version `YY.M.N` (UTC year and month without a leading zero, `N` counting deploys in that month from 0 — the first deploy of November 2026 is `26.11.0`; reused if the commit is already tagged with an `X.Y.Z` tag) and the artifact commit on `deploy-master` as `X.Y.Z-build`. A push to `deploy-master` (only ever done by that automated `deploy` job, not manually) is what triggers the `approve_deploy_to_live` gate — a **manual approval** required in the CircleCI UI before `deploy_to_live_general` runs and deploys via SSH to prod. So: normal feature work → commit + push to `master` → CI stops after the artifact lands on `deploy-master`; going live additionally requires clicking approve in CircleCI.
- **PHP version**: production and local both run PHP 8.5 (`composer.json` `config.platform.php` matches). `stripe/stripe-php` v15 (pinned by `commerce_stripe ^15`) calls the 8.5-deprecated `curl_close()` from a destructor at shutdown, after Drupal's DB connection is gone, which fatals with "StatementWrapperIterator ... null given" at the end of Stripe-calling pages. `settings.php` registers a narrow error handler that ignores only that deprecation; remove it once `commerce_stripe` allows a newer `stripe-php`.
- **Environment detection**: `settings.php` checks `$environment` (`local` vs `live`). Local overrides: Symfony Mailer routes to mailpit, trusted hosts open, stage_file_proxy pulls files from prod.
- **Fake time**: the Lando appserver installs `libfaketime` (see `.lando.yml`). Set/uncomment `FAKETIME` there to freeze or shift the container clock — useful for testing `commerce_recurring` renewal/billing behavior at future dates. Unset it afterwards.

### Drupal Config

- Config sync directory: `config/sync/`
- Config split (`drupal/config_split`) used for dev-only config: `config/sync/config_split.config_split.dev.yml`
- `.env` file loaded via `vlucas/phpdotenv` (see `load.environment.php`) — not committed

### Custom Modules (`web/modules/custom/`)

| Module | Purpose |
| --- | --- |
| `mcgreen_acres_custom` | General site customizations |
| `mcgreen_acres_store` | Store setup; ships default content (products, fees) via `default_content` |
| `mcgreen_subscription_payment` | Manual payment gateway support for subscriptions; sends payment-due emails at renewal instead of declining |
| `commerce_receipt_on_payment` | Sends order receipts when marked paid (not just on placement) |
| `custom_commerce_tip` | Overrides for `commerce_tip` module |
| `custom_commerce_login_pane` | Overrides checkout login pane for `auto_username` compatibility |
| `custom_user_tokens` | Custom token provider for conditional user data output |
| `juicer_capture` | Caches Juicer.io social feed markup |
| `duplicate_modal_block` | Renders an existing block inside a Bootstrap modal |
| `sales_chart` | Interactive daily/weekly/monthly revenue chart block for Commerce |
| `mcgreen_acres_quick_stock` | Permission-gated modal to quickly add/remove stock on a product variation, right from the product page |
| `mcgreen_order_payment` | Lets staff prepare admin-created orders (e.g. custom invoices) for the customer to pay directly, bypassing the storefront cart |
| `mcgreen_acres_newsletter_segments` | Simplenews recipient handler targeting subscribers by taxonomy tag (send-to/exclude) for per-issue segmentation |
| `custom_commerce_simplenews_checkout` | Overrides the Simplenews checkout pane to render as a single opt-in checkbox instead of a labeled newsletter list |
| `mcgreen_by_weight_orders` | Sells items by weight (meat, weighed produce) in fixed weight ranges priced at the range maximum. The card is held at checkout on the `stripe_by_weight` gateway (manual capture); staff enter packed weights on the order's "Weigh and charge" tab, which reprices each by-weight line at its actual weight (overridden unit price; the line title shows "(X lb @ $rate/lb)"; the authorized price stays in the item's `by_weight` data) and captures the exact amount (never more than authorized). Other lines on a mixed order are pinned at their ordered price. Variation price is computed as rate per lb × max weight on save. Also provides the "To weigh" orders view, a checkout notice pane, and the `order_excludes_variation_type` gateway condition that keeps `stripe_payment_element` off orders containing by-weight items. `stripe_by_weight` needs the same four Stripe credential overrides as `stripe_payment_element` in `settings.local.php`, plus its own Stripe webhook endpoint and signing secret |
| `mcgreen_cut_sheet` | Pig cut sheet: a standalone webform (`pig_cut_sheet`, `/form/pig-cut-sheet`, not tied to Commerce, no prices) where a customer picks how a whole or half hog is cut. Option lists are `webform_options` config (`cut_sheet_*`, currently Hess Meat Market's) so another processor is a config swap; a few option keys are a contract with the narrative and form states (see the module README). Provides the plain-English narrative, a staff-only bare print page (the "Cut sheet" tab on a submission; print or browser save-as-PDF, no PDF library), and `[webform_submission:cut_sheet]` / `[webform_submission:cut_sheet_print_url]` email tokens. Carries the `network_check` handler, and both email handlers (staff + customer copy) skip hosting-network submissions. The slaughter date is the `#default_value` of the `slaughter_date` element |
| `mcgreen_acres_newsletter_send_log` | Swaps the `simplenews.mailer` class for one that retries transient SMTP errors (dropped connection etc.) immediately and, if the transport looks down, aborts the batch so rows are retried next cron instead of being marked permanently failed. Logs every failed attempt (recipient, time, retry number, error) to `mcgreen_newsletter_send_attempt` and adds a "See details" link on `/node/N/simplenews` to `/node/N/simplenews/send-details`, where failed recipients can also be requeued. Tuning in `mcgreen_acres_newsletter_send_log.settings`; see the module README |
| `mcgreen_square_sync` | Copies in-person Square sales (production API, read-only) into a local table and shows them under Commerce > Square sales (`/admin/commerce/square-sales`; `drush square-sync:fetch`), suggesting which `square`-gateway order each card sale matches. Cash sales get no suggestion (Square "cash" also covers Zelle). Staff link sales to orders (several sales may share one order), map Square catalog items once on the Items tab (to a variation, to a by-weight product whose package size is picked from the line weight, to no product, or to a new unpublished `physical` product created from it) and map custom amounts per sale to a variation or an adjustment; all stored in their own tables, apart from the fetched sales. `ItemResolver` answers what a line becomes. Sales can also be ignored, or imported as new completed `default` orders (never "placed", so no emails or automatic stock; `SaleImporter` assigns the number, writes report entries, deducts stock only from the per-environment date in state `mcgreen_square_sync.stock_since`, and removes an imported order's payments and reports when it is deleted). Existing orders are never changed; there is no cron run. The token is a full-access personal access token read through the `square_access_token` Key from `private/keys/square_access_token.key`, which each environment needs its own copy of. See the module README |
| `mcgreen_webform_network_check` | Webform handler that classifies submitters by ASN (pass / relay / hosting) into a `network_check` value element; handlers that email or subscribe the submitter are conditioned on `!value: hosting`, so hosting-network submissions are silently quarantined. Cron emails a digest of new quarantines and purges them after `retention_days`. ASN lists and the GeoLite2-ASN `asn_database` path (absolute or relative to the Drupal root) live in `mcgreen_webform_network_check.settings` |

### Custom Theme (`web/themes/custom/mcgreen_acres_theme/`)

- **Active theme** (per `system.theme:default`): `mcgreen_acres_theme`. Based on `stable9`; no build pipeline — CSS/JS are plain files in `css/` and `js/`
- Libraries defined in `mcgreen_acres_theme.libraries.yml`
- `mcgreen_acres_theme.old/` is a committed backup of a prior version — not in use, don't edit
- **Admin theme**: `claro` (per `config/sync/system.theme.yml`). `drupal/gin` is a composer dependency but is not currently set as the admin theme

### Key Contrib Dependencies

- **Commerce stack**: `drupal/commerce`, `commerce_recurring`, `commerce_stripe`, `commerce_shipping`, `commerce_stock`, `commerce_tip`, `commerce_email`, `commerce_invoice`, `commerce_reports`
- **Subscriptions**: `commerce_recurring` with herd share monthly billing schedule
- **Email**: `symfony_mailer` + `mailsystem`; mailpit locally
- **Search**: `search_api`
- **Forms**: `webform` + `webform_mailchimp`
- **GeoIP**: `geoip2/geoip2` + `drupal/visitors` (custom fork for GeoIP2 support)
- **Rate limiting**: `drupal/crawler_rate_limit` with custom country-blocklist patch
- **Newsletter**: `simplenews` + `simplenews_stats`, `webform_simplenews_handler`, `commerce_simplenews_checkout`, and the custom newsletter modules above
- **Composer lenient**: `mglaman/composer-drupal-lenient` allows installing D11-incompatible modules; allow-list is in `composer.json` `extra.drupal-lenient.allowed-list` (e.g. `commerce_tip`, `pdf`, `user_csv_import`, `webform_ip_geo`)

### Pinned Versions

Version constraints in `composer.json` that hold a package back on purpose. Record the *why* here when adding a pin, and remove the entry when the pin is lifted.

_None currently._

### Patches

Many contrib modules are patched (see `composer.json` `extra.patches`). Notable ones:

- `drupal/commerce`: anonymous order creation from admin UI
- `drupal/commerce_recurring`: billing period display dates, customer cancel redirect
- `drupal/commerce_stripe`: reusable payment method fix, Express Checkout phone capture and order logging
- `drupal/commerce_tip`: multiple PHP 8.4 and D11 compatibility fixes
- `drupal/core`: active trail fix, Views aggregation fatal
- `drupal/entity_embed`: recursive rendering leak (matters when one newsletter embeds the same entity many times), empty display-settings warning
- `drupal/commerce_simplenews_checkout`: removes fatal D7 Variable API calls on install
- `drupal/visitors`: GeoIP2 null-location fields fatal fix
- `drupal/crawler_rate_limit`: custom country blocklist feature
- PHP 8.4 compatibility: `disqus`, `disqus-php`, `facebook_pixel`, `webform_ip_geo`, `dompdf`

Patches are applied automatically by `cweagans/composer-patches` during `lando composer install`. `composer-exit-on-patch-failure` is set to `true`, so if a patch no longer applies during a `lando composer update`, the entire update fails. When updating patched contrib modules, verify patches still apply or have been merged upstream.

### Security update watcher (`.claude/`)

`.claude/` is tracked selectively: the `drupal-update-report` skill, `.claude/security-watch/watch.py` and `.claude/settings.json`. Personal settings, worktrees, the skill's `cache/` and the watcher's runtime files are git-ignored, and `.circleci/scripts/deploy` removes `.claude` so it never reaches the artifact.

- **`/drupal-update-report`** (skill, user-invoked only): read-only pre-update report for outdated, enabled `drupal/*` packages: release notes, drupal.org security advisories matched against installed versions, and impact on patches, custom code and config. Never runs `composer update`.
- **`watch.py`** (**report-only**, never changes the repo): meant for a per-machine cron entry, `*/20 * * * 2-4 /usr/bin/python3 <checkout>/.claude/security-watch/watch.py`. It acts only on Wednesdays 11:00-16:09 America/Chicago. A cheap gate (advisory feed vs. installed versions, plus `composer audit --no-dev`; no Lando needed) decides whether anything new applies. If so, it starts one `claude -p` run that follows the skill under an allowlist-only permission file (no `composer update`, drush, git push, ssh, `@prod`) and writes `report.md` and `verdict.json`. It records whether the proposed auto-apply rule would have applied the update (core, Commerce, Stripe and custom modules are always human-only), sends a desktop notification and updates `.claude/security-watch/latest.md`.
- Runs live in `~/.local/state/mcgreen-security-watch/runs/`, not in the repo, because an unattended Claude session cannot write under `.claude/`. State is `.claude/security-watch/state.json` (delete it to re-analyse everything); each advisory gets two attempts before the watcher stops retrying.
- Permission-rule gotcha: path rules for file writes must be `Edit(//abs/path/**)`; `Write(path)` rules do not match.

### SEO / Content Workstream

Active SEO and content work lives at the repo root (currently untracked):

- `content-drafts/` — paired article files: `NN-brief-<slug>.md` (outline/approach) and `NN-copy-<slug>.md` (draft copy), e.g. raw-milk and herd-share articles, location pages
- `mcgreenacres-seo-audit-v2.md` — full site SEO audit with keyword targets and priorities
- `mockups/` — static HTML design mockups (homepage, location page); gitignored, along with root-level `*.gz` theme/db backups

### Drush Site Aliases

`drush/sites/self.site.yml` defines `@prod` — this file is not committed and contains server connection details.
