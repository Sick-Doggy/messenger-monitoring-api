FROM php:8.2-fpm-alpine

RUN apk add --no-cache bash git icu-dev libzip-dev \
    && docker-php-ext-install intl zip opcache \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Autoryzacja composer cache poza image
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache \
    COMPOSER_ALLOW_SUPERUSER=1

# Fallback przy pierwszym starcie: vendor instaluje się, jeśli go nie ma
COPY docker/php/fpm.ini /usr/local/etc/php/conf.d/app.ini

EXPOSE 9000
