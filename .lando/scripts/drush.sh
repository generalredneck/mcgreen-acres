#!/usr/bin/env bash

# Runs drush with --uri matching this checkout's Lando app name, so several
# checkouts (each with its own .lando.local.yml `name:`) can run side by side
# and still generate links (one-time logins, etc.) for the right site.
exec /app/vendor/bin/drush --root=/app/web --uri="http://${LANDO_APP_NAME}.${LANDO_DOMAIN}" "$@"
