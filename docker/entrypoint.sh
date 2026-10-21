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
# A missing APP_KEY used to mint a fresh random key on every container boot.
# Render free instances restart and sleep constantly, so every restart issued a
# new key, the encrypted session cookie could no longer be decrypted and every
# signed-in user was silently logged out. The key is now persisted on the
# Render disk when the environment does not provide one, so it survives
# restarts and redeploys. Setting APP_KEY in Render still wins and remains the
# recommended configuration.
if [ -z "${APP_KEY:-}" ]; then
  KEY_FILE=/var/data/app.key

  if [ -s "$KEY_FILE" ]; then
    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
    log "APP_KEY restored from $KEY_FILE."
  else
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
    export APP_KEY

    if grep -q " /var/data " /proc/mounts; then
      printf '%s\n' "$APP_KEY" > "$KEY_FILE"
      log "APP_KEY generated and persisted to $KEY_FILE."
    else
      warn "APP_KEY is not set in the Render environment and no disk is mounted"
      warn "at /var/data. A key was generated for this container only; it changes"
      warn "on every restart and signs every user out. Set APP_KEY in Render."
    fi
  fi
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
  if [ -z "${DB_DATABASE:-}" ]; then
    if grep -q " /var/data " /proc/mounts; then
      export DB_DATABASE=/var/data/database.sqlite
    else
      export DB_DATABASE=/var/www/html/database/database.sqlite
      warn "No disk is mounted at /var/data, so the SQLite database lives inside the"
      warn "container and is erased on every deploy. Attach a Render disk at /var/data."
    fi
  fi

  DB_FILE="$DB_DATABASE"
  mkdir -p "$(dirname "$DB_FILE")"
  touch "$DB_FILE"
  chown www-data:www-data "$DB_FILE" || true
  chmod 664 "$DB_FILE" || true
  log "SQLite database: $DB_FILE"
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
