#!/usr/bin/env bash
# Render (Docker) boot script for Pulse Tickets.
#
# Responsibilities: validate the runtime, provision the application key,
# prepare the database, migrate, install the platform and boot Apache.
# Anything that would leave the site half-working is fatal, so a broken
# deploy is reported by Render instead of silently serving errors.
set -euo pipefail

cd /var/www/html

log() { echo "[entrypoint] $*"; }
warn() { echo "[entrypoint] WARNING: $*" >&2; }
fail() { echo "[entrypoint] FATAL: $*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# 1. Runtime guards
# ---------------------------------------------------------------------------
php -r 'exit(PHP_VERSION_ID >= 80401 ? 0 : 1);' \
  || fail "PHP 8.4.1 or newer is required (symfony/http-foundation 8.x). This image runs PHP $(php -r 'echo PHP_VERSION;')."

php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);' \
  || fail "The pdo_sqlite extension is missing from this image."

log "PHP $(php -r 'echo PHP_VERSION;') on port ${PORT:-10000}."

# ---------------------------------------------------------------------------
# 2. Application key
# ---------------------------------------------------------------------------
if [ -z "${APP_KEY:-}" ]; then
  APP_KEY="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
  export APP_KEY
  warn "APP_KEY is not set in the Render environment."
  warn "A key was generated for this container only; it changes on every restart"
  warn "and invalidates sessions and signed URLs. Set APP_KEY in Render to fix it."
fi

# Render injects real environment variables, which always win over a .env file.
if [ ! -f .env ]; then
  printf 'APP_KEY=%s\n' "$APP_KEY" > .env
fi

# ---------------------------------------------------------------------------
# 3. Apache
# ---------------------------------------------------------------------------
sed -ri "s!^Listen 80\$!Listen ${PORT:-10000}!" /etc/apache2/ports.conf

# ---------------------------------------------------------------------------
# 4. Database
# ---------------------------------------------------------------------------
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
  mkdir -p "$(dirname "$DB_FILE")"
  touch "$DB_FILE"
  chown www-data:www-data "$DB_FILE" || true
  chmod 664 "$DB_FILE" || true

  if [ "$(dirname "$DB_FILE")" = "/var/data" ] && ! grep -q " /var/data " /proc/mounts; then
    warn "/var/data is not a mounted disk. The SQLite file lives inside the"
    warn "container and is erased on every deploy. Attach a Render disk at /var/data."
  fi
fi

# ---------------------------------------------------------------------------
# 5. Application
# ---------------------------------------------------------------------------
php artisan package:discover --ansi
php artisan migrate --force --no-interaction
php artisan ticketing:install --no-interaction
php artisan storage:link --force || warn "storage:link failed (public/storage may already exist)."
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

log "Ready."
exec apache2-foreground
