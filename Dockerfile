# syntax=docker/dockerfile:1.7
#
# One image, several roles. The production target serves HTTP on :8080 as the
# non-root www-data user. The same image runs the queue worker, scheduler and
# migrations by overriding the command (see docs/DEPLOYMENT.md).
#
# Targets:
#   development  PHP + nginx for local work with the source bind-mounted (compose.yaml)
#   production   Self-contained, optimized image for Kubernetes/EKS

ARG PHP_IMAGE=serversideup/php:8.4-fpm-nginx-v5.1.0
ARG NODE_IMAGE=node:22-bookworm-slim

############################################
# Frontend assets
############################################
FROM ${NODE_IMAGE} AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js tsconfig.json ./
COPY resources ./resources
COPY public ./public
RUN npm run build

############################################
# Shared PHP base
############################################
FROM ${PHP_IMAGE} AS base
WORKDIR /var/www/html
# Serve from Laravel's public/ directory; keep runtime logs on stdout/stderr.
ENV NGINX_WEBROOT=/var/www/html/public \
    LOG_OUTPUT_LEVEL=warn

############################################
# Development (source is bind-mounted over /var/www/html)
############################################
FROM base AS development
ARG USER_ID=1000
ARG GROUP_ID=1000
USER root
# sockets: required by the Pest browser plugin (a dev dependency).
RUN install-php-extensions sockets
# Match the container user to the host user so bind-mounted files stay owned by you.
RUN docker-php-serversideup-set-id www-data ${USER_ID}:${GROUP_ID} \
    && docker-php-serversideup-set-file-permissions --owner ${USER_ID}:${GROUP_ID} --service nginx
USER www-data
ENV PHP_OPCACHE_ENABLE=0 \
    AUTORUN_ENABLED=false

############################################
# Production dependencies (no dev packages)
############################################
FROM base AS vendor
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY --chown=www-data:www-data . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction \
    && rm -rf tests

############################################
# Production
############################################
FROM base AS production
COPY --link --chown=33:33 --from=vendor /var/www/html /var/www/html
COPY --link --chown=33:33 --from=assets /app/public/build /var/www/html/public/build

# Production defaults. Everything here can be overridden by the environment
# (Kubernetes env/ConfigMap/Secret). APP_KEY must always be supplied at runtime.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=info \
    PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=false \
    AUTORUN_LARAVEL_STORAGE_LINK=false \
    SSL_MODE=off

USER www-data
EXPOSE 8080
HEALTHCHECK --interval=10s --timeout=3s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1
