#!/bin/bash
set -e

# Pierwsze uruchomienie: brak vendor/ → composer install z wolumenu
if [ ! -d vendor/composer ] && [ -f composer.json ]; then
    composer install --no-interaction --prefer-dist --no-progress
fi

php-fpm
