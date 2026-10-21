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
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PORT=10000

RUN apt-get update \
 && apt-get install -y --no-install-recommends unzip libpq-dev \
 && docker-php-ext-install pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

COPY --from=vendor /app/vendor /var/www/html/vendor

WORKDIR /var/www/html
COPY . .

# The official php-apache image ships "AllowOverride None" for /var/www, which
# silently disables Laravel's public/.htaccess rewrite rules and turns every
# non-root URL into a 404. Switch it to All.
RUN set -eux; \
    sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf; \
    sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf; \
    sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf; \
    a2enmod rewrite headers; \
    if [ -f /usr/local/etc/php/php.ini-production ]; then cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini; fi; \
    printf '%s\n' 'opcache.enable=1' 'opcache.memory_consumption=192' 'opcache.max_accelerated_files=20000' 'opcache.validate_timestamps=0' 'max_execution_time=60' 'memory_limit=256M' >> /usr/local/etc/php/php.ini; \
    mkdir -p database; \
    chown -R www-data:www-data storage bootstrap/cache database; \
    chmod -R 775 storage bootstrap/cache database

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 10000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
