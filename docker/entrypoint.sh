#!/bin/sh
set -e

# Render replaces the container filesystem at every deploy. Refuse to start
# production without the attached persistent disk instead of silently writing
# uploads into the disposable container layer.
if [ "${APP_ENV:-}" = "production" ] && [ -n "${RENDER_SERVICE_ID:-}" ]; then
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
fi

exec "$@"
