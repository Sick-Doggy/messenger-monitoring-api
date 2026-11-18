<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Monitoring\MessageOccurrence;

final class MessageOccurrencePage
{
    /** @param list<MessageOccurrence> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $totalCount,
        public readonly int $page,
        public readonly int $itemsPerPage,
    ) {
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->totalCount / $this->itemsPerPage));
    }
}
