<?php

declare(strict_types=1);

namespace App\Filter;

use App\Monitoring\Stage;

final class MessageOccurrenceFilter
{
    public const DEFAULT_ITEMS_PER_PAGE = 25;

    /**
     * @param list<string> $tags dowolna zgodność (ANY)
     */
    public function __construct(
        public readonly ?string $className = null,
        public readonly ?string $monitoringId = null,
        public readonly ?string $processId = null,
        public readonly ?string $application = null,
        public readonly ?string $environment = null,
        public readonly ?Stage $currentStage = null,
        public readonly array $tags = [],
        public readonly ?\DateTimeImmutable $occurredAtFrom = null,
        public readonly ?\DateTimeImmutable $occurredAtTo = null,
        public readonly OccurrenceSortField $sortField = OccurrenceSortField::LastOccurredAt,
        public readonly SortDirection $sortDirection = SortDirection::Desc,
        public readonly int $page = 1,
        public readonly int $itemsPerPage = self::DEFAULT_ITEMS_PER_PAGE,
    ) {
    }
}
