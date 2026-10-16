#!/usr/bin/env bash
#
# نشر التطبيق بعد رفع الملفات إلى الخادم.
# Post-upload deploy for bashar.damatech.sy
#
# Usage (as root):  ./deploy.sh
#         skip asset build:  ./deploy.sh --no-assets
#
# The step that matters most is `artisan migrate --force`: on 2026-09-14 code
# using the SoftDeletes trait was uploaded without its migration, and every
# authenticated request 500'd on a missing users.deleted_at column.

set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_USER="bashardamatechsy"
APP_GROUP="www-data"
FPM_SERVICE="php8.4-fpm"

# The app user's login HOME (/var/www/bashar.damatech.sy) is root-owned, so
# composer and npm have nowhere to write their caches. Use the site tmp dir.
CACHE_HOME="$(dirname "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)")/tmp"
export HOME="$CACHE_HOME"
export COMPOSER_HOME="$CACHE_HOME/.composer"
export NPM_CONFIG_CACHE="$CACHE_HOME/.npm"

BUILD_ASSETS=1
[[ "${1:-}" == "--no-assets" ]] && BUILD_ASSETS=0

cd "$APP_DIR"

step() { printf '\n\033[1;34m==> %s\033[0m\n' "$1"; }
fail() { printf '\n\033[1;31m!! deploy failed at: %s\033[0m\n' "${1:-unknown}" >&2; }

# On failure the app deliberately STAYS in maintenance mode. A migration that
# died halfway leaves the schema inconsistent, and serving exchange traffic
# against it risks bad data - a human should look before the site comes back.
cleanup() {
    local rc=$?
    if (( rc != 0 )); then
        fail "$CURRENT_STEP"
        printf '\033[1;31mThe site is STILL IN MAINTENANCE MODE (503) on purpose.\033[0m\n' >&2
        printf 'Check the step above, then bring it back with:\n  cd %s && php artisan up\n' "$APP_DIR" >&2
    fi
    exit $rc
}
CURRENT_STEP="startup"
trap cleanup EXIT

# Run artisan as the web user so no cache file ends up owned by root —
# php-fpm runs as $APP_USER and rewrites compiled views at runtime.
if [[ "$(id -u)" -eq 0 ]]; then
    as_app() { setpriv --reuid="$APP_USER" --regid="$APP_GROUP" --clear-groups \
        env HOME="$HOME" COMPOSER_HOME="$COMPOSER_HOME" NPM_CONFIG_CACHE="$NPM_CONFIG_CACHE" "$@"; }
elif [[ "$(id -un)" == "$APP_USER" ]]; then
    as_app() { "$@"; }
else
    echo "Run this as root or as $APP_USER (currently $(id -un))." >&2
    exit 1
fi

artisan() { as_app php artisan "$@"; }

CURRENT_STEP="maintenance mode"
step "Enabling maintenance mode"
artisan down --retry=60

CURRENT_STEP="composer install"
step "Installing PHP dependencies"
as_app composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

if (( BUILD_ASSETS )); then
    CURRENT_STEP="asset build"
    step "Building frontend assets"
    as_app npm ci
    as_app npm run build
fi

CURRENT_STEP="database migration"
step "Running database migrations (central & tenants)"
artisan migrate --force
artisan tenants:migrate --force

CURRENT_STEP="cache rebuild"
step "Rebuilding caches"
artisan config:clear
artisan cache:clear
artisan config:cache
artisan route:cache
artisan view:cache

CURRENT_STEP="permissions"
step "Normalising ownership"
if [[ "$(id -u)" -eq 0 ]]; then
    chown -R "$APP_USER:$APP_GROUP" storage bootstrap/cache
fi
chmod -R ug+rwX storage bootstrap/cache

CURRENT_STEP="php-fpm reload"
step "Reloading PHP-FPM (clears opcache)"
if [[ "$(id -u)" -eq 0 ]]; then
    systemctl reload "$FPM_SERVICE"
else
    echo "Not root - skipping. Run: sudo systemctl reload $FPM_SERVICE"
fi

CURRENT_STEP="maintenance mode"
step "Disabling maintenance mode"
artisan up

CURRENT_STEP="done"
step "Deploy complete"
artisan migrate:status | tail -n 5 || true
