#syntax=docker/dockerfile:1

# Baza wspólna dla dev i build: rozszerzenia PHP + composer.
# Debian (trixie, glibc) + FrankenPHP default — mniej egzotyki niż musl/alpine.
FROM php:8.4-fpm-trixie AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions intl opcache mongodb

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# DEVELOPMENT: kod i vendor z bind mountu hosta
FROM base AS dev

COPY docker/php/php.ini /usr/local/etc/php/php.ini

# BUILD PROD: pełna instalacja zależności produkcyjnych
FROM base AS build

COPY --chown=www-data:www-data . /var/www

RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts

# Kompilowany boot: .env nie wchodzi do obrazu (.dockerignore), a SymfonyRuntime
# wymaga odczytu .env przy starcie (PathException). --empty = tylko APP_ENV=prod,
# wartości przychodzą w runtime z środowiska (nic z devowego .env nie trafia do obrazu).
RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-env prod --empty

# PRODUCTION: obraz samowystarczalny — FrankenPHP (Caddy + PHP w jednym binarium)
# serwuje HTTP sam, bez nginx/fpm. Baza NIE jest taka sama jak dev (ZTS, runtime Caddy).
FROM dunglas/frankenphp:1.14.0-php8.4 AS prod

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# rozszerzenia jak w base; install-php-extensions czyści build-deps sam
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip curl \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions intl opcache mongodb

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY --from=build --chown=www-data:www-data /var/www /var/www

COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/php/php-prod.ini /usr/local/etc/php/php.ini

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    XDG_CONFIG_HOME=/tmp/caddy/config \
    XDG_DATA_HOME=/tmp/caddy/data

# port >1024, żeby usługa działała jako www-data (TLS/80 po stronie proxy konsumenta)
EXPOSE 8080

USER www-data

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl --fail -s http://127.0.0.1:8080/health || exit 1
