# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Vendor stage — resolves the autoloader only.
# The composer image ships its own PHP version, so platform checks are skipped
# here; the real requirement is enforced at boot by the runtime guard in
# docker/entrypoint.sh (PHP >= 8.4.1, because symfony/http-foundation 8.x uses
# PHP 8.4 property hooks).
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --ignore-platform-reqs

# ---------------------------------------------------------------------------
# Runtime stage — PHP 8.4 is mandatory, not optional: symfony/http-foundation
# 8.x declares "php": ">=8.4.1" and uses property hooks, which are a parse
# error on PHP 8.3.
# ---------------------------------------------------------------------------
FROM php:8.4-apache
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    PORT=10000

RUN apt-get update \
 && apt-get install -y --no-install-recommends unzip libpq-dev \
 && docker-php-ext-install pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

COPY --from=vendor /app/vendor /var/www/html/vendor

WORKDIR /var/www/html
COPY . .

# The vhost is written by hand with a literal DocumentRoot. The usual
# `sed s!/var/www/html!${APACHE_DOCUMENT_ROOT}!` trick relies on Apache
# interpolating an environment variable, which silently falls back to
# /var/www/html and publishes the whole application directory instead of
# public/ — every URL then 404s and every source file is downloadable.
#
# AllowOverride All is required as well: with "AllowOverride None" Apache
# ignores Laravel's public/.htaccess, so the rewrite rules never run.
RUN set -eux; \
    printf '%s\n' \
        '<VirtualHost *:80>' \
        '    ServerName localhost' \
        '    DocumentRoot /var/www/html/public' \
        '    DirectoryIndex index.php index.html' \
        '' \
        '    <Directory /var/www/html/public>' \
        '        Options -Indexes +FollowSymLinks' \
        '        AllowOverride All' \
        '        Require all granted' \
        '    </Directory>' \
        '' \
        '    ErrorLog /var/log/apache2/error.log' \
        '    CustomLog /var/log/apache2/access.log combined' \
        '</VirtualHost>' \
        > /etc/apache2/sites-available/000-default.conf; \
    a2ensite 000-default.conf; \
    a2enmod rewrite headers; \
    sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf; \
    grep -q 'DocumentRoot /var/www/html/public' /etc/apache2/sites-available/000-default.conf; \
    if [ -f /usr/local/etc/php/php.ini-production ]; then cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini; fi; \
    printf '%s\n' 'opcache.enable=1' 'opcache.memory_consumption=192' 'opcache.max_accelerated_files=20000' 'opcache.validate_timestamps=0' 'max_execution_time=60' 'memory_limit=256M' >> /usr/local/etc/php/php.ini; \
    mkdir -p database; \
    chown -R www-data:www-data storage bootstrap/cache database; \
    chmod -R 775 storage bootstrap/cache database

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 10000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["sh", "-c", "php artisan migrate:fresh --seed --force && apache2-foreground"]
