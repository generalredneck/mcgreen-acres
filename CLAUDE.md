# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

McGreen Acres is a Drupal 11 e-commerce site for a farm/herd share business. It uses Drupal Commerce with recurring subscriptions (herd shares) and a custom theme. Stripe is the only live payment gateway (`stripe_card`, `stripe_payment_element`); the `square`, `check`, `cash`, and `zelle` gateways use Commerce's `manual` plugin so staff can record offline payments in the order system — keeping every income source trackable at an administrative level (reporting, revenue charts). The local dev environment is managed via Lando.

## Common Commands

All `drush` and `composer` commands must be run via Lando:

```bash
lando drush <command>        # Drush commands
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

## Architecture

### Hosting & Deployment

- **Local**: Lando with MariaDB 11.4, PHP 8.4, mailpit for email capture. Site URL: `https://mcgreen-acres.lndo.site` Mailpit: https://mail-mcgreen-acres.lndo.site
- **Production**: Shared hosting at `mcgreenacres.com` — connection details are in `drush/sites/self.site.yml` (not committed)
- **CI/CD**: CircleCI builds an artifact (strips `.git` dirs/tests, commits to `deploy-<branch>`), then deploys via SSH to prod after manual approval. Deploy branch naming: `deploy-master` triggers live deploy.
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

### SEO / Content Workstream

Active SEO and content work lives at the repo root (currently untracked):

- `content-drafts/` — paired article files: `NN-brief-<slug>.md` (outline/approach) and `NN-copy-<slug>.md` (draft copy), e.g. raw-milk and herd-share articles, location pages
- `mcgreenacres-seo-audit-v2.md` — full site SEO audit with keyword targets and priorities
- `mockups/` — static HTML design mockups (homepage, location page); gitignored, along with root-level `*.gz` theme/db backups

### Drush Site Aliases

`drush/sites/self.site.yml` defines `@prod` — this file is not committed and contains server connection details.
