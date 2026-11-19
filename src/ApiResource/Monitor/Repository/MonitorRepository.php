<?php

declare(strict_types=1);

namespace App\ApiResource\Monitor\Repository;

use App\ApiResource\Monitor\Document\Monitor;
use App\ApiResource\Monitor\Dto\MonitorFilter;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

/**
 * @extends DocumentRepository<Monitor>
 */
final class MonitorRepository extends DocumentRepository
{
    /**
     * Strona kolekcji: sort createdAt desc (stabilny — upsert nie zmienia createdAt),
     * filtr wg MonitorFilter, paginacja offset.
     *
     * @return list<Monitor>
     */
    public function search(MonitorFilter $filter): array
    {
        /** @var list<Monitor> $monitors */
        $monitors = $this->findBy(
            $this->criteria($filter),
            ['createdAt' => 'desc'],
            $filter->itemsPerPage,
            ($filter->page - 1) * $filter->itemsPerPage,
        );

        return $monitors;
    }

    /**
     * Liczba dokumentów pasujących do filtra (totalItems).
     */
    public function countBy(MonitorFilter $filter): int
    {
        return (int) $this->getDocumentManager()
            ->getDocumentCollection(Monitor::class)
            ->countDocuments($this->criteria($filter));
    }

    /**
     * Jedyne miejsce mapujące MonitorFilter na kryteria zapytania Mongo.
     *
     * @return array<string, mixed>
     */
    private function criteria(MonitorFilter $filter): array
    {
        $criteria = [];

        if (null !== $filter->monitoringId) {
            $criteria['monitoringId'] = $filter->monitoringId;
        }

        if (null !== $filter->processId) {
            $criteria['processId'] = $filter->processId;
        }

        if ([] !== $filter->tags) {
            // filtry wielowartościowe: dokument musi mieć wszystkie tagi (AND)
            $criteria['tags'] = ['$all' => $filter->tags];
        }

        return $criteria;
    }
}
