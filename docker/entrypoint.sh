#!/bin/sh
set -e

# Render replaces the container filesystem at every deploy. Keep the mounted
# Render disk for SQLite and local fallback storage, but allow remote ImageKit
# uploads to bypass the local-disk requirement when the service is configured
# for remote media hosting.
if [ "${APP_ENV:-}" = "production" ] && [ -n "${RENDER_SERVICE_ID:-}" ]; then
    has_imagekit=false

    if [ -n "${IMAGEKIT_PUBLIC_KEY:-}" ] && [ -n "${IMAGEKIT_PRIVATE_KEY:-}" ] && [ -n "${IMAGEKIT_URL_ENDPOINT:-}" ]; then
        has_imagekit=true
    fi

    if [ "$has_imagekit" = "false" ]; then
        export PUBLIC_STORAGE_ROOT="${PUBLIC_STORAGE_ROOT:-/var/data/app/public}"
        database_path="${DB_DATABASE:-/var/data/database.sqlite}"

        if ! mountpoint -q /var/data; then
            echo "FATAL: Render persistent disk is not mounted at /var/data; refusing to start with ephemeral upload storage." >&2
            exit 1
        fi

        case "$database_path" in
            /var/data/*) ;;
            *)
                echo "FATAL: production database must be stored on the persistent disk under /var/data (got $database_path)." >&2
                exit 1
                ;;
        esac

        case "$PUBLIC_STORAGE_ROOT" in
            /var/data/*) ;;
            *)
                echo "FATAL: production uploads must be stored under the persistent disk at /var/data (got $PUBLIC_STORAGE_ROOT)." >&2
                exit 1
                ;;
        esac

        mkdir -p "$PUBLIC_STORAGE_ROOT"

        if [ ! -w "$PUBLIC_STORAGE_ROOT" ]; then
            echo "FATAL: persistent upload directory is not writable: $PUBLIC_STORAGE_ROOT" >&2
            exit 1
        fi

        chown www-data:www-data /var/data "$PUBLIC_STORAGE_ROOT"
        chmod 775 /var/data "$PUBLIC_STORAGE_ROOT"

        if [ -f "$database_path" ]; then
            chown www-data:www-data "$database_path"
        fi

        echo "Persistent database enabled: $database_path"
        echo "Persistent uploads enabled: $PUBLIC_STORAGE_ROOT"
    else
        echo "ImageKit media enabled; keeping Render disk for SQLite/local fallback only."
    fi
fi

exec "$@"
