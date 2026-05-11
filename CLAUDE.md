# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

McGreen Acres is a Drupal 11 e-commerce site for a farm/herd share business. It uses Drupal Commerce with recurring subscriptions (herd shares), Stripe/Square payment gateways, and a custom theme. The local dev environment is managed via Lando.

## Common Commands

All `drush` and `composer` commands must be run via Lando:

```bash
lando drush <command>        # Drush commands
lando composer <command>     # Composer commands
lando build                  # Full rebuild: composer install + db sync from prod + config import
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

## Architecture

### Hosting & Deployment

- **Local**: Lando with MariaDB 11.4, PHP 8.4, mailpit for email capture. Site URL: `http://mcgreen-acres.lndo.site`
- **Production**: Shared hosting at `mcgreenacres.com` — connection details are in `drush/sites/self.site.yml` (not committed)
- **CI/CD**: CircleCI builds an artifact (strips `.git` dirs/tests, commits to `deploy-<branch>`), then deploys via SSH to prod after manual approval. Deploy branch naming: `deploy-master` triggers live deploy.
- **Environment detection**: `settings.php` checks `$environment` (`local` vs `live`). Local overrides: Symfony Mailer routes to mailpit, trusted hosts open, stage_file_proxy pulls files from prod.

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

### Custom Theme (`web/themes/custom/mcgreen_acres_theme/`)

- Based on `stable9`; no build pipeline — CSS/JS are plain files in `css/` and `js/`
- Libraries defined in `mcgreen_acres_theme.libraries.yml`

### Key Contrib Dependencies

- **Commerce stack**: `drupal/commerce`, `commerce_recurring`, `commerce_stripe`, `commerce_shipping`, `commerce_stock`, `commerce_tip`, `commerce_email`, `commerce_invoice`, `commerce_reports`
- **Subscriptions**: `commerce_recurring` with herd share monthly billing schedule
- **Email**: `symfony_mailer` + `mailsystem`; mailpit locally
- **Search**: `search_api`
- **Forms**: `webform` + `webform_mailchimp`
- **GeoIP**: `geoip2/geoip2` + `drupal/visitors` (custom fork for GeoIP2 support)
- **Rate limiting**: `drupal/crawler_rate_limit` with custom country-blocklist patch

### Patches

Many contrib modules are patched (see `composer.json` `extra.patches`). Notable ones:

- `drupal/commerce`: anonymous order creation from admin UI, circular reference fix
- `drupal/commerce_recurring`: billing period display dates, customer cancel redirect
- `drupal/commerce_stripe`: reusable payment method fix
- `drupal/commerce_tip`: multiple PHP 8.4 and D11 compatibility fixes
- `drupal/core`: active trail, views aggregation fatal, email error suppression
- `drupal/crawler_rate_limit`: custom country blocklist feature

Patches are applied automatically by `cweagans/composer-patches` during `lando composer install`. `composer-exit-on-patch-failure` is set to `true`, so if a patch no longer applies during a `lando composer update`, the entire update fails. When updating patched contrib modules, verify patches still apply or have been merged upstream.

### Drush Site Aliases

`drush/sites/self.site.yml` defines `@prod` — this file is not committed and contains server connection details.
