#syntax=docker/dockerfile:1

# Baza wspólna dla dev i prod: rozszerzenia PHP + composer
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
        icu \
        libzip \
        git \
    && apk add --no-cache --virtual .build-deps \
        icu-dev \
        libzip-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j "$(nproc)" intl opcache \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && apk del .build-deps

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

# PRODUCTION: vendor wbudowany, kod bez vendor/tests, brak bind mountów
FROM base AS prod

COPY docker/php/php-prod.ini /usr/local/etc/php/php.ini

COPY --from=build --chown=www-data:www-data /var/www /var/www

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

USER www-data
