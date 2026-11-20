# Konwencje projektu

Ustalenia architektoniczne obowiązujące w całym API. Dokument żywy — zmiana konwencji = aktualizacja tego pliku w tym samym commitcie.

## Stack i jakość kodu

- Symfony 7.4, API Platform 5, MongoDB ODM 3, PHP 8.4, Docker Compose (php-fpm + nginx + mongo).
- PHPStan **level 8** — 0 błędów, CS-Fixer `@Symfony`, PHPUnit 11 od pierwszego commita.
- Testy jednostkowe poza Dockerem (sandbox/lokalnie), testy funkcjonalne w Dockerze (wymagają ext-mongodb).

## Struktura katalogów — model per zasób

Wszystko związane z zasobem leży w jednym katalogu:

- `src/ApiResource/{Zasób}/{Zasób}.php` — definicja API Resource atrybutem PHP (`#[ApiResource]`)
- `src/ApiResource/{Zasób}/Document/` — encja (dokument MongoDB ODM); jedyne źródło prawdy
- `src/ApiResource/{Zasób}/Dto/` — Input / PatchInput / Output / Filter
- `src/ApiResource/{Zasób}/Repository/` — repozytorium ODM
- `src/ApiResource/{Zasób}/State/` — providerzy i procesory (po jednym na operację)

Kod generyczny, niezwiązany z żadnym zasobem, ląduje globalnie: `src/Attribute/FilterParam/`, `src/State/` (np. `CollectionPaginator`). Nie tworzymy wspólnych `src/Document` ani `src/Entity` — nowy zasób zakładamy zawsze katalogiem, kopiując układ `Monitor`.

`services.yaml` wyłącza z autowire `src/{Attribute,ApiResource/*/Dto,ApiResource/*/Document,ApiResource/*/Repository}/` — DTO, dokumenty i klasy repozytoriów nie są usługami.

## Modele i operacje

Warstwy dla zasobu `Monitor` (wzorzec dla każdego następnego zasobu):

- `Document/Monitor.php` — mapowanie ODM włącznie z ID. Gdy identyfikator nadaje klient, `#[ODM\Id(strategy: 'none', type: 'string')]` i to pole jest `_id` oraz częścią URI.
- `Dto/MonitorInput` — POST i PUT (pełna semantyka: brak/puste pole = default `''`/`null` → walidacja → **422**, nie 400).
- `Dto/MonitorPatchInput` — PATCH; wszystkie pola nullable; **konwencja: `null = bez zmiany`**; scalanie w procesorze przez `if (null !== $input->x)`. Konsekwencja: nie da się przez PATCH ustawić pola optional na `null` — tylko pełne PUT.
- `Dto/MonitorOutput` — wspólne wyjście wszystkich operacji odczytu/zapisu.
- Identyfikator zasobu jest nietykalny: nie występuje w PatchInput; PUT z mismatchem body vs URI → 422.

Semantyka POST: jeżeli zasób jest rozpoznawalny po ID nadawanym przez klienta, POST działa jak upsert (istnieje → pełne nadpisanie, odpowiedź 201).

## State — providerzy i procesory

- **Po jednym Provider/Processor na operację** (`CreateMonitorProcessor`, `UpdateMonitorProcessor`, `MonitorItemProvider`, `MonitorCollectionProvider`…). Nie robimy jednego „mega procesora" z rozgałęzieniami po `$operation`.
- Persist jest własny (procesor przez `DocumentManager`), nie korzystamy z `PersistProcessor` API Platform — pułapka: klasy final/ODM nie przechodzą przez domyślny pipeline.

## Filtry kolekcji

- Filtr to **value object**, nie klasa filtra API Platform: `Dto/{Zasób}Filter` (readonly, pola filtrów, brak defaultów na property — tylko w konstruktorze).
- Parsowanie query string (`$context['filters']`) ma **jedno** miejsce: `State/MonitorFilterFactory::fromContext()`. Tam też clampy (np. długość listy tagów ≤ 100).
- Repozytorium przyjmuje gotowy obiekt filtrów: `search({Zasób}Filter)` + osobne `countBy()`.
- Metadane filtrów do OpenAPI: atrybut generyczny `App\Attribute\FilterParam\FilterParam` (description/schema/required) na polach klasy filtrów; dekorator `FilterParametersMetadataFactory` (`decorates: api_platform.metadata.resource.metadata_collection_factory`, `decoration_priority: -100`) przekłada je na `QueryParameter`. Zasób podpina filtr przez `extraProperties: ['filter' => {Zasób}Filter::class]`.
- Semantyka filtru po tagach: **AND** (`$all`), syntax `?tags[]=a&tags[]=b` lub `?tags=a,b`.
- Semantyka filtru po etapie stanu (`MonitorState`, `?stage=`): **OR** (`$in`) — stan ma dokładnie jeden etap, więc AND nie miałby sensu; syntax jak przy tagach.

## Paginacja

- Offsetowa, wariant Hydra: `page` + `itemsPerPage` + `totalItems`; `paginationClientItemsPerPage: true` — klient może ograniczyć rozmiar strony.
- Generyczny paginator: `src/State/CollectionPaginator.php` (`@template T of object`, `@implements PaginatorInterface<T>` + `IteratorAggregate`; nie używać `int,T`).
- Kolekcje sortowane stabilnie po `createdAt desc`.
  - Wyjątek: historia stanów (`MonitorState`, `/api/monitors/{occurrenceId}/states`) — chronologicznie po `occurredAtTs asc` (sekwencja zdarzeń w kolejności, w jakiej nastąpiły, niezależnie od kolejności dojścia raportów).
- Tryb cursor/`?since` robimy **wyłącznie jako osobną operację** (osobny `uriTemplate`, np. `/monitors/stream` + dedykowane Output) — nie przekształcamy offsetowej paginacji in place.

## Nowy zasób — checklist

1. Katalog `src/ApiResource/{Zasób}/` wg układu `Monitor` (Resource + Document + Dto + Repository + State).
2. `Document` z mapowaniem ODM + indeksy pod filtry z tabeli pól.
3. Dto: Input (+PatchInput jeśli potrzeba PATCH) + Output + Filter.
4. Procesor/Provider per operacja; repo `search(Filter)` + `countBy()`.
5. Atrybuty `FilterParam` na polach filtrów, `extraProperties` na resource.
6. Zmiana tabeli pól = Document + Dto + procesor + repo + testy jednocześnie.
