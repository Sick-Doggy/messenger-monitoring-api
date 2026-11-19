COMPOSE = docker compose

.PHONY: up down build composer stan cs cs-check test console

build:
	$(COMPOSE) build

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

composer-install:
	$(COMPOSE) run --rm php composer install

stan:
	$(COMPOSE) run --rm php vendor/bin/phpstan analyse --memory-limit=1G

cs:
	$(COMPOSE) run --rm php vendor/bin/php-cs-fixer fix

cs-check:
	$(COMPOSE) run --rm php vendor/bin/php-cs-fixer fix --dry-run --diff

test:
	$(COMPOSE) run --rm -e APP_ENV=test php vendor/bin/phpunit

console:
	$(COMPOSE) run --rm php bin/console
