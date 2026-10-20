#!/bin/sh
# Entrypoint for the server image (app, queue and scheduler share it).
#
# Caches the configuration, routes and views for this container, optionally applies migrations (the app container
# does, once, when RUN_MIGRATIONS=true), then hands over to the command.
set -eu

cd /var/www/backend

php artisan optimize >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
