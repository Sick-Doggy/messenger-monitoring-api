<?php

declare(strict_types=1);

namespace App\ApiResource\MonitorState\Repository;

use App\ApiResource\MonitorState\Document\MonitorState;
use App\ApiResource\MonitorState\Dto\MonitorStateFilter;
use Doctrine\ODM\MongoDB\Query\Builder;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

/**
 * @extends DocumentRepository<MonitorState>
 */
final class MonitorStateRepository extends DocumentRepository
{
    /**
     * Zapis nowego stanu albo zwrot istniejącego, jeśli ten sam stan był już zgłoszony.
     *
     * Jedna operacja findAndModify z upsertem po kluczu naturalnym (occurrenceId + stage +
     * occurredAtTs + application) — ponowne zgłoszenie nie tworzy duplikatu. Przy równoczesnych
     * identycznych zgłoszeniach dodatkowo chroni unikalny indeks z mapowania dokumentu.
     */
    public function createOrGet(MonitorState $state): MonitorState
    {
        $queryBuilder = $this->naturalKey($this->createQueryBuilder()->findAndUpdate(), $state)
            ->upsert()
            ->returnNew()
            ->field('id')->setOnInsert($state->getId())
            ->field('occurredAt')->setOnInsert($state->getOccurredAt())
            ->field('environment')->setOnInsert($state->getEnvironment())
            ->field('busName')->setOnInsert($state->getBusName())
            ->field('transportName')->setOnInsert($state->getTransportName())
            ->field('handlerName')->setOnInsert($state->getHandlerName())
            ->field('errorClass')->setOnInsert($state->getErrorClass())
            ->field('errorMessage')->setOnInsert($state->getErrorMessage());

        $stored = $queryBuilder->getQuery()->execute();
        \assert($stored instanceof MonitorState);

        return $stored;
    }

    /** Czy inny wpis tego wystąpienia ma już ten sam klucz naturalny (PUT/PATCH → 409). */
    public function hasOtherWithSameKey(MonitorState $state): bool
    {
        return null !== $this->naturalKey($this->createQueryBuilder(), $state)
            ->field('id')->notEqual($state->getId())
            ->getQuery()
            ->getSingleResult();
    }

    /** Wpis tylko wtedy, gdy należy do wskazanego wystąpienia (zagnieżdżone URI). */
    public function findInMonitor(string $occurrenceId, string $id): ?MonitorState
    {
        return $this->findOneBy(['id' => $id, 'occurrenceId' => $occurrenceId]);
    }

    /**
     * Strona historii: sort chronologiczny occurredAtTs asc (wyjątek od createdAt desc — patrz
     * CONVENTIONS.md, Paginacja), filtr wg MonitorStateFilter, paginacja offset.
     *
     * @return list<MonitorState>
     */
    public function search(MonitorStateFilter $filter): array
    {
        /** @var list<MonitorState> $states */
        $states = $this->findBy(
            $this->criteria($filter),
            ['occurredAtTs' => 'asc'],
            $filter->itemsPerPage,
            ($filter->page - 1) * $filter->itemsPerPage,
        );

        return $states;
    }

    /**
     * Liczba stanów pasujących do filtra (totalItems).
     */
    public function countBy(MonitorStateFilter $filter): int
    {
        return (int) $this->getDocumentManager()
            ->getDocumentCollection(MonitorState::class)
            ->countDocuments($this->criteria($filter));
    }

    /** Usunięcie całej historii wystąpienia (kaskada przy DELETE Monitora). */
    public function deleteOfMonitor(string $occurrenceId): void
    {
        $this->createQueryBuilder()
            ->remove() // usuwa wszystkie pasujące dokumenty
            ->field('occurrenceId')->equals($occurrenceId)
            ->getQuery()
            ->execute();
    }

    /**
     * Jedyne miejsce mapujące MonitorStateFilter na kryteria zapytania Mongo.
     *
     * @return array<string, mixed>
     */
    private function criteria(MonitorStateFilter $filter): array
    {
        $criteria = ['occurrenceId' => $filter->occurrenceId];

        if ([] !== $filter->stage) {
            // filtr wielowartościowy: dowolny z podanych etapów (OR)
            $criteria['stage'] = ['$in' => $filter->stage];
        }

        if (null !== $filter->application) {
            $criteria['application'] = $filter->application;
        }

        return $criteria;
    }

    /** Jedyne miejsce definiujące klucz naturalny stanu (= unikalny indeks w mapowaniu). */
    private function naturalKey(Builder $queryBuilder, MonitorState $state): Builder
    {
        return $queryBuilder
            ->field('occurrenceId')->equals($state->getOccurrenceId())
            ->field('stage')->equals($state->getStage()->value)
            ->field('occurredAtTs')->equals($state->getOccurredAtTs())
            ->field('application')->equals($state->getApplication());
    }
}
