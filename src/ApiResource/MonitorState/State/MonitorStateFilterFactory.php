<?php

declare(strict_types=1);

namespace App\ApiResource\MonitorState\State;

use App\ApiResource\MonitorState\Dto\MonitorStateFilter;

/**
 * Parsuje parametry query (context filters) do MonitorStateFilter.
 * Jedyne miejsce znające "brudny" kształt HTTP: stage jako ?stage[]=… albo ?stage=a,b,
 * clampy paginacji, pomijanie pustych wartości.
 */
final class MonitorStateFilterFactory
{
    public const DEFAULT_ITEMS_PER_PAGE = 25;
    public const MAX_ITEMS_PER_PAGE = 100;

    /**
     * @param array<string, mixed> $context
     */
    public function fromContext(string $occurrenceId, array $context): MonitorStateFilter
    {
        $filters = $context['filters'] ?? [];

        return $this->fromFilters($occurrenceId, is_array($filters) ? $filters : []);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function fromFilters(string $occurrenceId, array $filters): MonitorStateFilter
    {
        return new MonitorStateFilter(
            occurrenceId: $occurrenceId,
            stage: $this->stages($filters['stage'] ?? null),
            application: $this->cleanString($filters['application'] ?? null),
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
     * Nieznany etap zostaje w filtrze — zapytanie zwróci wtedy pustą listę, a nie całą historię.
     *
     * @return list<string>
     */
    private function stages(mixed $stages): array
    {
        if (is_string($stages)) {
            $stages = explode(',', $stages);
        }

        if (!is_array($stages)) {
            return [];
        }

        $cleaned = [];
        foreach ($stages as $stage) {
            $stage = $this->cleanString($stage);
            if (null !== $stage && !in_array($stage, $cleaned, true)) {
                $cleaned[] = $stage;
            }
        }

        return $cleaned;
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
