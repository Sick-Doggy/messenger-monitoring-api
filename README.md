# Messenger Monitoring API

REST API for monitoring messenger traffic, built with **Symfony 7.4** + **API Platform 5** + **MongoDB ODM** on **PHP 8.4**.

Requests to the API require the `X-Api-Key` header (`API_KEY` environment variable). The OpenAPI docs endpoints (`/api/docs`, `/api/docs.json`) are public.

## Quick start (Docker, development)

```bash
docker compose build
docker compose up -d
docker compose run --rm php composer install
```

The API is available at `http://localhost:8080/api`.

## Example: consuming the published image

The production image (`target: prod`) is published on Docker Hub. It is fully self-contained: it runs **FrankenPHP** (Caddy with embedded PHP) and serves HTTP itself on port `8080` — no external nginx/fpm needed. TLS/HTTPS is the job of a reverse proxy in front of the container, if you have one.

A minimal `docker-compose.yml`:

```yaml
services:

  php:
    image: sickdoggy/messenger-monitoring-api:latest
    ports:
      - "${WEB_PORT:-8080}:8080"
    environment:
      APP_ENV: prod
      APP_DEBUG: "0"
      APP_SECRET: ${APP_SECRET:?Set APP_SECRET in .env or environment}
      API_KEY: ${API_KEY:?Set API_KEY (sent as X-Api-Key header)}
      MONGODB_URI: ${MONGODB_URI:-mongodb://mongodb:27017}
      MONGODB_DB: ${MONGODB_DB:-messenger_monitoring}
    depends_on:
      - mongodb
    restart: unless-stopped

  mongodb:
    image: mongo:8
    volumes:
      - mongodb_data:/data/db:rw
    command: ['--quiet']
    restart: unless-stopped

volumes:
  mongodb_data:
```

Then:

```bash
docker compose up -d
curl -H "X-Api-Key: <your-key>" http://localhost:8080/api/docs.json
```

The image ships with a tiny health endpoint (`/health`, always `200`) outside the API firewall — useful for container `HEALTHCHECK` and load balancer probes.

## Project structure

```
src/
  ApiResource/
    Monitor/                    # Monitor resource — everything in one place
      Monitor.php               # resource id + operation definitions
      Document/Monitor.php      # MongoDB document (pure domain, no API Platform)
      Dto/                      # Input / PatchInput / Output / Filter (+ #[FilterParam])
      Repository/               # MonitorRepository: queries + criteria
      State/                    # Providers, Processors, MonitorFilterFactory
  Attribute/
    FilterParam/                # #[FilterParam] + factory pushing it into OpenAPI (generic)
      FilterParam.php
      FilterParametersMetadataFactory.php
  Security/                     # X-Api-Key authenticator + user
  State/
    CollectionPaginator.php     # paginator for custom providers (generic)
  Kernel.php
```

A new resource is a directory under `src/ApiResource/{Name}/` containing `{Name}.php`, `Document/`, `Dto/`, `Repository/`, `State/`. Fields of `{Name}Filter` annotated with `#[FilterParam]` produce the OpenAPI query-parameter documentation automatically.

## Quality tools & tests

Run from the Makefile (Docker), or locally if you have PHP 8.4 with the `mongodb` + `intl` extensions:

```bash
make stan        # PHPStan, level 8
make cs          # PHP-CS-Fixer (@Symfony ruleset)
make test        # PHPUnit
```

Functional tests require a running MongoDB (`compose.override.yaml` / localhost:27017).

## Publishing the image (Docker Hub)

The production image is published by the `.github/workflows/docker.yml` workflow on push of a **git tag `v*`** (e.g. `git tag v1.0.0 && git push origin v1.0.0`):

1. job `test` — builds the dev image, runs composer install + PHPUnit with a mongo:8 service container,
2. job `publish` — buildx multi-arch (`linux/amd64`, `linux/arm64`), pushes to Docker Hub
   as `{{version}}`, `{{major.minor}}` and `latest`.

Required GitHub repository secrets: `DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`
(Docker Hub access token). The image namespace (`sickdoggy/messenger-monitoring-api`)
is set in `env.IMAGE` of the workflow.

Publishing without CI — manually:

```bash
docker build --target prod -t sickdoggy/messenger-monitoring-api:local .
```

## Configuration

Main variables (`.env`, override in `.env.local`):

| Variable       | Default                     | Description                                        |
|----------------|-----------------------------|----------------------------------------------------|
| `MONGODB_URI`  | `mongodb://localhost:27017` | MongoDB address (Docker: `mongodb://mongodb:27017`) |
| `MONGODB_DB`   | `messenger_monitoring`      | Database name                                      |
| `API_KEY`      | —                           | Key sent in the `X-Api-Key` header                 |
| `APP_ENV`      | `dev`                       | Symfony environment                                |
| `APP_SECRET`   | —                           | Symfony secret (required in production)            |
