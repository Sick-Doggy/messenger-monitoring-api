<?php

declare(strict_types=1);

namespace App\Filter;

use App\Monitoring\Stage;

final class MessageOccurrenceFilterFactory
{
    public const MAX_ITEMS_PER_PAGE = 100;

    private const SUPPORTED_PARAMETERS = [
        'className', 'monitoringId', 'processId', 'application', 'environment',
        'currentStage', 'tags', 'occurredAt[after]', 'occurredAt[before]', 'sort', 'page', 'itemsPerPage',
    ];

    private const SUPPORTED_SORT_FIELDS = ['lastOccurredAt', 'className', 'monitoringId'];

    /**
     * @param array<string, mixed> $query
     */
    public function fromQuery(array $query): MessageOccurrenceFilter
    {
        $this->assertSupportedParameters(array_keys($query));

        $sort = $this->parseSort($query['sort'] ?? null);

        return new MessageOccurrenceFilter(
            className: $this->optionalString($query, 'className'),
            monitoringId: $this->optionalString($query, 'monitoringId'),
            processId: $this->optionalString($query, 'processId'),
            application: $this->optionalString($query, 'application'),
            environment: $this->optionalString($query, 'environment'),
            currentStage: $this->currentStage($query),
            tags: $this->tags($query),
            occurredAtFrom: $this->occurredAtFrom($query),
            occurredAtTo: $this->occurredAtTo($query),
            sortField: $sort[0],
            sortDirection: $sort[1],
            page: $this->page($query),
            itemsPerPage: $this->itemsPerPage($query),
        );
    }

    /** @param list<string> $parameters */
    private function assertSupportedParameters(array $parameters): void
    {
        $unknown = array_diff($parameters, self::SUPPORTED_PARAMETERS);

        if ([] !== $unknown) {
            throw FilterException::invalidValue(
                implode(', ', $unknown),
                'unknown',
                self::SUPPORTED_PARAMETERS,
            );
        }
    }

    /** @param array<string, mixed> $query */
    private function optionalString(array $query, string $key): ?string
    {
        $value = self::asString($query, $key);

        return null === $value || '' === $value ? null : $value;
    }

    /** @param array<string, mixed> $query */
    private static function asString(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @param array<string, mixed> $query */
    private function currentStage(array $query): ?Stage
    {
        $value = self::asString($query, 'currentStage');

        if (null === $value || '' === $value) {
            return null;
        }

        return $this->matchStage($value);
    }

    private function matchStage(string $value): Stage
    {
        return Stage::tryFrom($value) ?? throw FilterException::invalidValue(
            'currentStage',
            $value,
            array_map(static fn (Stage $stage): string => $stage->value, Stage::cases()),
        );
    }

    /**
     * @param array<string, mixed> $query
     * @return list<string>
     */
    private function tags(array $query): array
    {
        $value = self::asString($query, 'tags');

        if (null === $value || '' === trim($value)) {
            return [];
        }

        $tags = array_map(static fn (string $tag): string => trim($tag), explode(',', $value));
        $tags = array_values(array_unique(array_filter($tags, static fn (string $tag): bool => '' !== $tag)));
        $list = [];

        foreach ($tags as $tag) {
            $list[] = $tag;
        }

        return $list;
    }

    /** @param array<string, mixed> $query */
    private function occurredAtFrom(array $query): ?\DateTimeImmutable
    {
        return $this->date($query, 'occurredAt[after]');
    }

    /** @param array<string, mixed> $query */
    private function occurredAtTo(array $query): ?\DateTimeImmutable
    {
        return $this->date($query, 'occurredAt[before]');
    }

    /** @param array<string, mixed> $query */
    private function date(array $query, string $key): ?\DateTimeImmutable
    {
        $value = self::asString($query, $key);

        if (null === $value || '' === $value) {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw FilterException::invalidValue($key, $value, ['RFC3339 date']);
        }

        return $date;
    }

    /**
     * Konwencja jak w briefie: "-field" = najpierw najnowsze (DESC).
     * Bez parametru sortowania lista podaje ostatnio aktywne na początku.
     *
     * @return array{0: OccurrenceSortField, 1: SortDirection}
     */
    private function parseSort(mixed $sort): array
    {
        if (!is_string($sort) || '' === $sort) {
            return [OccurrenceSortField::LastOccurredAt, SortDirection::Desc];
        }

        $field = OccurrenceSortField::tryFrom(ltrim($sort, '+-')) ?? throw FilterException::invalidValue(
            'sort',
            $sort,
            self::SUPPORTED_SORT_FIELDS,
        );

        return [$field, str_starts_with($sort, '-') ? SortDirection::Desc : SortDirection::Asc];
    }

    /** @param array<string, mixed> $query */
    private function page(array $query): int
    {
        $value = self::asString($query, 'page');

        if (null === $value || '' === $value) {
            return 1;
        }

        if (!ctype_digit($value)) {
            throw FilterException::invalidValue('page', $value);
        }

        $page = (int) $value;

        if ($page < 1) {
            throw FilterException::invalidValue('page', $value);
        }

        return $page;
    }

    /** @param array<string, mixed> $query */
    private function itemsPerPage(array $query): int
    {
        $value = self::asString($query, 'itemsPerPage');

        if (null === $value || '' === $value) {
            return MessageOccurrenceFilter::DEFAULT_ITEMS_PER_PAGE;
        }

        if (!ctype_digit($value) || (int) $value < 1 || (int) $value > self::MAX_ITEMS_PER_PAGE) {
            throw FilterException::invalidValue('itemsPerPage', $value, ['1...' . self::MAX_ITEMS_PER_PAGE]);
        }

        return (int) $value;
    }
}
