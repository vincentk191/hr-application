# PHP + Node image for local development.
#
# Deliberately `cli`, not `fpm`: the dev container runs `artisan serve`, which needs no Nginx
# and keeps the stack to one process per service. Production uses Nginx + PHP-FPM (see
# ../docs/context.md §5.8) and will add a separate stage here when Phase 7 lands.
#
# Pinned to 8.4 to match the VPS and the local Herd install; composer.json allows ^8.3.
FROM php:8.4-cli AS base

# libpq-dev  -> pdo_pgsql (PostgreSQL is the only supported database here)
# libzip-dev -> zip, needed by Composer and later by Laravel Excel
# libicu-dev -> intl, for Indonesian date and number formatting
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Composer runs as root inside the container; silence its own warning about that rather than
# inventing a user whose UID would not match the Windows bind mount anyway.
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

# The backend is mounted here. The frontend is mounted beside it at /var/www/frontend by
# docker-compose.yml; the Vite container runs from there.
WORKDIR /var/www/backend


# Node ships in the same image as PHP, and not merely for convenience: the Wayfinder Vite
# plugin shells out to `php artisan wayfinder:generate` every time the dev server starts, so a
# Node-only container cannot run Vite at all. One image, two services.
FROM node:24-trixie-slim AS node

FROM base AS dev

# Copied from the official Node image rather than installed from a distro repo, so the version
# is pinned and matches the host (24.x). Only the Node binary and the npm package tree are
# taken — copying all of /usr/local/bin would clobber the PHP binaries sitting there.
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node /usr/local/bin/node /usr/local/bin/node
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && node -v \
    && npm -v

# xdebug is intentionally absent: it slows the test suite noticeably and nothing needs it yet.
# Add it here, enabled through an env var, if step debugging becomes worth the cost.

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
