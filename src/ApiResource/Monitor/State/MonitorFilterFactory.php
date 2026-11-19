<?php

declare(strict_types=1);

namespace App\ApiResource\Monitor\State;

use App\ApiResource\Monitor\Dto\MonitorFilter;

/**
 * Parsuje parametry query (context filters) do MonitorFilter.
 * Jedyne miejsce znające "brudny" kształt HTTP: tags jako ?tags[]=… albo ?tags=a,b,
 * clampy paginacji, pomijanie pustych wartości.
 */
final class MonitorFilterFactory
{
    public const DEFAULT_ITEMS_PER_PAGE = 25;
    public const MAX_ITEMS_PER_PAGE = 100;

    /**
     * @param array<string, mixed> $context
     */
    public function fromContext(array $context): MonitorFilter
    {
        $filters = $context['filters'] ?? [];

        return $this->fromFilters(is_array($filters) ? $filters : []);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function fromFilters(array $filters): MonitorFilter
    {
        return new MonitorFilter(
            monitoringId: $this->cleanString($filters['monitoringId'] ?? null),
            processId: $this->cleanString($filters['processId'] ?? null),
            tags: $this->tags($filters['tags'] ?? null),
            page: $this->positiveInt($filters['page'] ?? null, 1),
            itemsPerPage: $this->clampedItemsPerPage($filters['itemsPerPage'] ?? null),
        );
    }

    private function cleanString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * @return list<string>
     */
    private function tags(mixed $tags): array
    {
        if (is_string($tags) && '' !== $tags) {
            $tags = explode(',', $tags);
        }

        if (!is_array($tags)) {
            return [];
        }

        $trimmed = array_map(
            static fn (mixed $tag): ?string => is_string($tag) ? trim($tag) : null,
            $tags,
        );

        /* @var list<string> */
        return array_values(array_filter(
            $trimmed,
            static fn (?string $tag): bool => null !== $tag && '' !== $tag,
        ));
    }

    private function positiveInt(mixed $value, int $fallback): int
    {
        return is_numeric($value)
            ? max(1, (int) $value)
            : $fallback;
    }

    private function clampedItemsPerPage(mixed $value): int
    {
        $itemsPerPage = is_numeric($value)
            ? (int) $value
            : self::DEFAULT_ITEMS_PER_PAGE;

        return min(self::MAX_ITEMS_PER_PAGE, max(1, $itemsPerPage));
    }
}
