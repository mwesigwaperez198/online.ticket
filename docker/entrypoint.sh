#!/usr/bin/env bash
set -euo pipefail

sed -ri "s!^Listen 80\$!Listen ${PORT:-10000}!" /etc/apache2/ports.conf

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
  php artisan key:generate --force
fi

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
  mkdir -p "$(dirname "${DB_FILE}")"
  touch "${DB_FILE}"
  chown www-data:www-data "${DB_FILE}" || true
fi

php artisan package:discover --ansi || true
php artisan migrate --force --no-interaction || true
php artisan storage:link --force || true
php artisan config:cache --no-interaction || true
php artisan route:cache --no-interaction || true

exec apache2-foreground
