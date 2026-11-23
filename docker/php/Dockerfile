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

# Boot prod: SymfonyRuntime wymaga pliku .env (PathException gdy go nie ma — .env nie
# wchodzi do obrazu przez .dockerignore). Piszemy minimalny .env jawny: tylko APP_ENV=prod,
# wartości przychodzą w runtime ze środowiska; nic z devowego .env nie trafia do obrazu.
RUN printf 'APP_ENV=prod\n' > /var/www/.env

# Ślepę env dla KOMEND builda (assets:install, cache:warmup — każda bin/console bootuje
# kernel i kompilacja kontenera rozwija %env()% ze config/). Defaulty z .env repo, żadna
# nie jest sekretem. ENV nie przecieka do stage prod (osobny FROM); realne wartości w runtime.
ENV MONGODB_URI=mongodb://mongodb:27017 \
    MONGODB_DB=messenger_monitoring \
    APP_SECRET=buildtime-stub-not-a-secret \
    API_KEY=buildtime-stub-not-a-secret \
    DEFAULT_URI=http://localhost \
    CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'

# Assets z bundlów (Swagger UI CSS/JS itd.): composer --no-scripts nie odpala assets:install,
# więc kopiujemy jawnie z vendora — public/bundles w pełnej wersji (z vendor/api-platform).
RUN COMPOSER_ALLOW_SUPERUSER=1 php bin/console assets:install --env=prod

# Cache wypalany w buildzie: katalog var/cache/prod wchodzi do obrazu (runtime nie tworzy go
# sam), a błąd bootowania aplikacji wywala BUILD zamiast umierać po cichu w runtime.
RUN COMPOSER_ALLOW_SUPERUSER=1 php bin/console cache:warmup --env=prod --no-interaction

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
