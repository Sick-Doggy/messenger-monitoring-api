<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Filter\MessageOccurrenceFilter;
use App\Monitoring\MessageOccurrence;

interface MessageOccurrenceRepositoryInterface
{
    public function findByFilter(MessageOccurrenceFilter $filter): MessageOccurrencePage;

    public function get(string $occurrenceId): ?MessageOccurrence;

    public function save(MessageOccurrence $occurrence): void;
}
