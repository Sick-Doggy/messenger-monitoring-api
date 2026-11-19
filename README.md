# Messenger Monitoring API

REST API do monitorowania komunikatorów, zbudowana na **Symfony 7.4** + **API Platform 5** + **MongoDB ODM**, PHP **8.4**.

## Szybki start (Docker)

```bash
docker compose build
docker compose up -d
docker compose run --rm php composer install
```

API jest dostępne pod `http://localhost:8080/api`.

## Struktura (zasób zamknięty w jednym katalogu)

```
src/
  ApiResource/
    Monitor/                    # zasób Monitor — całość w jednym miejscu
      Monitor.php               # identyfikator zasobu + definicja operacji
      Document/Monitor.php      # dokument MongoDB (czysta domena, bez API Platform)
      Dto/                      # Input / PatchInput / Output / Filter (+ #[FilterParam])
      Repository/               # MonitorRepository: zapytania + kryteria
      State/                    # Providerzy, Procesory, MonitorFilterFactory
  Attribute/
    FilterParam/                # #[FilterParam] + factory wypychająca je do OpenAPI (generik)
      FilterParam.php
      FilterParametersMetadataFactory.php
  State/
    CollectionPaginator.php     # paginator dla custom providerów (generik)
  Kernel.php
```

Nowy zasób = katalog w `src/ApiResource/{Name}/` z `{Name}.php`, `Document/`,
`Dto/`, `Repository/`, `State/`; atrybutami `#[FilterParam]` opisujesz pola
`{Name}Filter` i dokumentacja Swaggera robi się sama.

Narzędzia jakości i testy uruchamiasz z Makefile (Docker) lub lokalnie, jeśli masz PHP 8.4 z rozszerzeniami `mongodb` + `intl`:

```bash
make stan        # PHPStan, poziom 8
make cs          # PHP-CS-Fixer (ruleset @Symfony)
make test        # PHPUnit
```

Testy funkcyjne wymagają działającego MongoDB (`compose.override.yaml` / localhost:27017).

## Publikacja obrazu (Docker Hub)

Obraz produkcyjny PHP-FPM (`target: prod`) publikowany jest workflowem
`.github/workflows/docker.yml` na push **git taga `v*`** (np. `git tag v1.0.0 && git push origin v1.0.0`):

1. job `test` — buduje obraz dev, odpala composer install + PHPUnit z serwisem mongo:8,
2. job `publish` — buildx multi-arch (`linux/amd64`, `linux/arm64`), push do Docker Hub
   jako `{{wersja}}`, `{{major.minor}}` i `latest`.

Wymagane sekrety repozytorium GitHub: `DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`
(access token z Docker Hub). Namespace obrazu (`sickdoggy/messenger-monitoring-api`)
ustawiany jest w `env.IMAGE` workflowa.

Prod lokalnie z opublikowanego obrazu:

```bash
docker compose -f compose.prod.yaml up -d   # APP_SECRET musi przyjść z .env/env
```

Bez CI — ręcznie:

```bash
docker build --target prod -t sickdoggy/messenger-monitoring-api:local .
```

## Konfiguracja

Główne zmienne (`/ .env`, nadpisyj w `.env.local`):

| zmienna         | wartość domyślna              | opis                          |
|-----------------|-------------------------------|-------------------------------|
| `MONGODB_URI`   | `mongodb://localhost:27017`   | adres MongoDB (Docker: `mongodb://mongodb:27017`) |
| `MONGODB_DB`    | `messenger_monitoring`        | nazwa bazy                    |
| `APP_ENV`       | `dev`                         | środowisko Symfony            |
