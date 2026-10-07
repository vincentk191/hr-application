#!/usr/bin/env bash
# Container entrypoint for the dev image.
#
# Keeps a fresh clone to one command: `docker compose up` installs dependencies and prepares
# the app, rather than expecting the host to have PHP or Composer available.
set -euo pipefail

BACKEND=/var/www/backend

# Every service shares this entrypoint, but only the backend needs preparing, and the Vite
# service runs from /var/www/frontend. So the preparation happens in a subshell and the final
# `exec` runs from whatever directory the container was started in.
(
    cd "$BACKEND"

    # vendor/ is a named volume, so it is empty on the first run. Only install when there is
    # nothing there.
    if [ ! -f vendor/autoload.php ]; then
        echo "[entrypoint] vendor/ is empty, running composer install..."
        composer install --no-interaction --prefer-dist
    fi

    if [ ! -f .env ]; then
        echo "[entrypoint] no .env, copying .env.example..."
        cp .env.example .env
    fi

    # APP_KEY is required before almost anything else will boot.
    if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
        echo "[entrypoint] generating APP_KEY..."
        php artisan key:generate --force
    fi

    # The bind mount can arrive without these, and Laravel fails late and confusingly if so.
    mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache
    chmod -R a+w storage bootstrap/cache 2>/dev/null || true
)

exec "$@"
