<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Filter\OccurrenceSortField;
use App\Filter\MessageOccurrenceFilter;
use App\Filter\SortDirection;
use App\Monitoring\MessageOccurrence;
use App\Monitoring\OccurrenceStatus;

final class InMemoryMessageOccurrenceRepository implements MessageOccurrenceRepositoryInterface
{
    /** @var array<string, MessageOccurrence> */
    private array $occurrences = [];

    public function save(MessageOccurrence $occurrence): void
    {
        $this->occurrences[$occurrence->occurrenceId] = $occurrence;
    }

    public function get(string $occurrenceId): ?MessageOccurrence
    {
        return $this->occurrences[$occurrenceId] ?? null;
    }

    public function findByFilter(MessageOccurrenceFilter $filter): MessageOccurrencePage
    {
        $matching = array_values(array_filter(
            $this->occurrences,
            static fn (MessageOccurrence $occurrence): bool => self::matches($occurrence, $filter),
        ));

        usort($matching, $this->comparator($filter));

        $offset = ($filter->page - 1) * $filter->itemsPerPage;

        return new MessageOccurrencePage(
            array_slice($matching, $offset, $filter->itemsPerPage),
            count($matching),
            $filter->page,
            $filter->itemsPerPage,
        );
    }

    private static function matches(MessageOccurrence $occurrence, MessageOccurrenceFilter $filter): bool
    {
        return self::equals($filter->className, $occurrence->className)
            && self::equals($filter->monitoringId, $occurrence->monitoringId())
            && self::equals($filter->processId, $occurrence->processId())
            && ($filter->currentStage === null || $occurrence->currentStage() === $filter->currentStage)
            && self::hasAnyTag($occurrence, $filter->tags)
            && self::afterOrEqual($occurrence, $filter->occurredAtFrom)
            && self::beforeOrEqual($occurrence, $filter->occurredAtTo)
            && self::hasStatusValue($occurrence->statuses(), 'application', $filter->application)
            && self::hasStatusValue($occurrence->statuses(), 'environment', $filter->environment);
    }

    private static function equals(?string $expected, ?string $actual): bool
    {
        return null === $expected || $expected === $actual;
    }

    /** @param list<string> $tags */
    private static function hasAnyTag(MessageOccurrence $occurrence, array $tags): bool
    {
        return [] === $tags || [] !== array_intersect($tags, $occurrence->tags());
    }

    /**
     * Filtry application/environment działają po stanach — ta sama sprawa jest
     * widziana z kilku aplikacji, więc wystarczy jeden pasujący stan.
     *
     * @param list<OccurrenceStatus> $statuses
     */
    private static function hasStatusValue(array $statuses, string $property, ?string $expected): bool
    {
        if (null === $expected) {
            return true;
        }

        foreach ($statuses as $status) {
            $actual = 'application' === $property ? $status->application : $status->environment;

            if ($actual === $expected) {
                return true;
            }
        }

        return false;
    }

    private static function lastOccurredAt(MessageOccurrence $occurrence): \DateTimeImmutable
    {
        return $occurrence->lastOccurredAt() ?? new \DateTimeImmutable('@0');
    }

    private static function afterOrEqual(MessageOccurrence $occurrence, ?\DateTimeImmutable $from): bool
    {
        return null === $from || self::lastOccurredAt($occurrence) >= $from;
    }

    private static function beforeOrEqual(MessageOccurrence $occurrence, ?\DateTimeImmutable $to): bool
    {
        return null === $to || self::lastOccurredAt($occurrence) <= $to;
    }

    private function comparator(MessageOccurrenceFilter $filter): \Closure
    {
        return static function (MessageOccurrence $a, MessageOccurrence $b) use ($filter): int {
            $comparison = match ($filter->sortField) {
                OccurrenceSortField::LastOccurredAt => self::lastOccurredAt($a) <=> self::lastOccurredAt($b),
                OccurrenceSortField::ClassName => $a->className <=> $b->className,
                OccurrenceSortField::MonitoringId => self::byMonitoringId($a, $b, $filter->sortDirection),
            };

            if (0 !== $comparison) {
                return SortDirection::Desc === $filter->sortDirection ? -$comparison : $comparison;
            }

            return self::lastOccurredAt($a) <=> self::lastOccurredAt($b);
        };
    }

    /** null-e idą zawsze na koniec, niezależnie od kierunku sortowania. */
    private static function byMonitoringId(MessageOccurrence $a, MessageOccurrence $b, SortDirection $direction): int
    {
        if (null === $a->monitoringId() && null === $b->monitoringId()) {
            return 0;
        }
        if (null === $a->monitoringId()) {
            return 1;
        }
        if (null === $b->monitoringId()) {
            return -1;
        }

        $comparison = $a->monitoringId() <=> $b->monitoringId();

        return SortDirection::Desc === $direction ? -$comparison : $comparison;
    }
}
