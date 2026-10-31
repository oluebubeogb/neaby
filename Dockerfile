# syntax=docker/dockerfile:1.7
# ─────────────────────────────────────────────────────────────
#  neaby — production image (app only)
#  PostgreSQL and Redis run as SEPARATE Coolify resources.
#  Listens on :3123 (nginx + php-fpm, serversideup/php).
# ─────────────────────────────────────────────────────────────

ARG PHP_VERSION=8.4

# ── 1. PHP base with the extensions neaby needs ──────────────
FROM serversideup/php:${PHP_VERSION}-fpm-nginx AS base
USER root
# pdo_pgsql, redis, zip, opcache, pcntl ship with the base image.
RUN install-php-extensions intl gd exif bcmath
ENV PHP_OPCACHE_ENABLE=1 \
    PHP_UPLOAD_MAX_FILE_SIZE=100M \
    PHP_POST_MAX_SIZE=100M \
    PHP_MEMORY_LIMIT=256M \
    NGINX_CLIENT_MAX_BODY_SIZE=100M \
    NGINX_HTTP_PORT=3123 \
    AUTORUN_ENABLED=false \
    SSL_MODE=off \
    LOG_CHANNEL=stderr
WORKDIR /var/www/html

# ── 2. Composer dependencies (cached on composer.lock) ───────
FROM base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

# ── 3. Front-end build (Vite). Ziggy is imported from vendor ──
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY --from=vendor /var/www/html/vendor ./vendor
COPY vite.config.js tailwind.config.js postcss.config.js jsconfig.json ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ── 4. Final runtime image ───────────────────────────────────
FROM base AS app
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
COPY --chmod=755 docker-entrypoint.sh /etc/entrypoint.d/60-neaby.sh
USER root
# Queue worker + scheduler as supervised s6 longruns inside the same container.
COPY --chmod=755 docker/s6/neaby-queue/ /etc/s6-overlay/s6-rc.d/neaby-queue/
COPY --chmod=755 docker/s6/neaby-scheduler/ /etc/s6-overlay/s6-rc.d/neaby-scheduler/
RUN for svc in neaby-queue neaby-scheduler; do \
      mkdir -p /etc/s6-overlay/s6-rc.d/$svc/dependencies.d && touch /etc/s6-overlay/s6-rc.d/$svc/dependencies.d/base; \
      reg=0; \
      for b in /etc/s6-overlay/user-bundles.d/user/contents.d /etc/s6-overlay/s6-rc.d/user/contents.d; do \
        if [ -d "$b" ]; then touch "$b/$svc"; reg=1; fi; \
      done; \
      if [ "$reg" = 0 ]; then mkdir -p /etc/s6-overlay/s6-rc.d/user/contents.d && touch /etc/s6-overlay/s6-rc.d/user/contents.d/$svc; fi; \
    done; true
RUN rm -f bootstrap/cache/*.php public/hot public/storage \
 && mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
             storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && composer dump-autoload --optimize --no-dev --no-interaction \
 && php artisan storage:link \
 && chown -R www-data:www-data storage bootstrap/cache public \
 && chmod -R ug+rwX storage bootstrap/cache
USER www-data
EXPOSE 3123
# Base image healthcheck hits nginx; Coolify should probe /up (see README).
