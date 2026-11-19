# Messenger Monitoring API

REST API do monitorowania komunikatorów, zbudowana na **Symfony 7.4** + **API Platform 5** + **MongoDB ODM**, PHP **8.4**.

## Szybki start (Docker)

```bash
docker compose build
docker compose up -d
docker compose run --rm php composer install
```

API jest dostępne pod `http://localhost:8080/api`.

## Struktura (modułowa, DDD-lite)

```
src/
  Monitoring/                # moduł monitoringu
    Document/Monitor.php     # dokument MongoDB + zasób API Platform
    Enum/MessengerProvider.php
    Repository/MonitorRepository.php
  Shared/                    # wspólne komponenty między modułami
    ApiKey/ApiKeyGenerator.php
  Kernel.php
```

Nowe moduły to nowe katalogi w `src/` (np. `Messenger/`, `User/`). Mapowania MongoDB są skonfigurowane per moduł w `config/packages/doctrine_mongodb.yaml` — dodając moduł, dopisz jego `dir`/`prefix`.

Narzędzia jakości i testy uruchamiasz z Makefile (Docker) lub lokalnie, jeśli masz PHP 8.4 z rozszerzeniami `mongodb` + `intl`:

```bash
make stan        # PHPStan, poziom max
make cs          # PHP-CS-Fixer (ruleset @Symfony)
make test        # PHPUnit
```

Testy funkcyjne wymagają działającego MongoDB (`compose.override.yaml` / localhost:27017).

## Konfiguracja

Główne zmienne (`/ .env`, nadpisyj w `.env.local`):

| zmienna         | wartość domyślna              | opis                          |
|-----------------|-------------------------------|-------------------------------|
| `MONGODB_URI`   | `mongodb://localhost:27017`   | adres MongoDB (Docker: `mongodb://mongodb:27017`) |
| `MONGODB_DB`    | `messenger_monitoring`        | nazwa bazy                    |
| `APP_ENV`       | `dev`                         | środowisko Symfony            |
