# PHP + Node image for local development.
#
# Deliberately `cli`, not `fpm`: the dev container runs `artisan serve`, which needs no Nginx
# and keeps the stack to one process per service. Production uses Nginx + PHP-FPM (see
# ../docs/context.md §5.8) and will add a separate stage here when Phase 7 lands.
#
# Pinned to 8.4 to match the VPS and the local Herd install; composer.json allows ^8.3.
# The PHP image the shared base starts from: the CLI for development, php-fpm for the server stack.
ARG PHP_IMAGE=php:8.4-cli
FROM ${PHP_IMAGE} AS base

# libpq-dev  -> pdo_pgsql (PostgreSQL is the only supported database here)
# libzip-dev -> zip, needed by Composer and by Laravel Excel
# libpng-dev -> gd, which PhpSpreadsheet (under Laravel Excel) requires
# libicu-dev -> intl, for Indonesian date and number formatting
# ghostscript -> shrinks uploaded PDFs (config/uploads.php); without it they are stored as uploaded
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        ghostscript \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
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

# ---- Server stack (docker-compose.server.yml) ----------------------------------------------------------------------
#
# Built from the repository alone, nothing mounted: the code, the production Composer install and the built frontend
# are all inside. Build it with --build-arg PHP_IMAGE=php:8.4-fpm for the app image (the compose file does).

# The dev image has PHP and Node together, which the build needs (the Wayfinder plugin runs `php artisan`).
FROM dev AS assets
COPY backend /var/www/backend
COPY frontend /var/www/frontend
WORKDIR /var/www/backend
# A throwaway key and an in-memory database: booting the app to generate routes needs neither for real.
ENV APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    APP_ENV=production \
    DB_CONNECTION=sqlite \
    DB_DATABASE=:memory: \
    CACHE_STORE=array \
    SESSION_DRIVER=array \
    QUEUE_CONNECTION=sync
# Packages for development (the example-data seeders need the faker package) are installed only when asked for:
# build with COMPOSER_DEV=true on a testing server, never on a real one.
ARG COMPOSER_DEV=false
RUN if [ "$COMPOSER_DEV" = "true" ]; then composer install --no-interaction --prefer-dist --optimize-autoloader;     else composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader; fi
WORKDIR /var/www/frontend
RUN npm ci && npm run build
# The public disk is exposed at /storage by a link that points into the shared storage volume.
RUN ln -sfn ../storage/app/public /var/www/backend/public/storage \
    && rm -rf /var/www/frontend/node_modules

# PHP-FPM with the application baked in.
FROM base AS server
COPY docker/php/php-server.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf
COPY docker/server/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
COPY --from=assets /var/www/backend /var/www/backend
WORKDIR /var/www/backend
RUN rm -f .env \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# Nginx with the built public folder, so it serves the app's files itself and hands only PHP to the app container.
FROM nginx:1.27-alpine AS web
COPY --from=assets /var/www/backend/public /var/www/backend/public
COPY docker/server/nginx.conf.template /etc/nginx/templates/default.conf.template
# Not a template (no ${...} placeholders): copied straight in, not through envsubst.
COPY docker/server/nginx-locations.conf /etc/nginx/snippets/hr-locations.conf
# The VPN https block is kept out of the templates folder and added by a start-up script only when it can work (a
# hostname is set and its certificate exists), so a missing certificate cannot stop Nginx from starting. The script's line
# endings are forced to LF: a Windows checkout would otherwise break its shebang.
COPY docker/server/vpn-tls.conf.template /opt/hr/vpn-tls.conf.template
COPY docker/server/15-vpn-tls.sh /docker-entrypoint.d/15-vpn-tls.sh
RUN sed -i 's/\r$//' /docker-entrypoint.d/15-vpn-tls.sh /opt/hr/vpn-tls.conf.template \
    && chmod +x /docker-entrypoint.d/15-vpn-tls.sh
