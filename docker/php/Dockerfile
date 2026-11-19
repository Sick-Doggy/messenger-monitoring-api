FROM php:8.4-fpm-alpine

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

COPY docker/php/php.ini /usr/local/etc/php/php.ini

WORKDIR /var/www
