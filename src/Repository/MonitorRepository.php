<?php

declare(strict_types=1);

namespace App\Repository;

use App\Document\Monitor;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

/**
 * @extends DocumentRepository<Monitor>
 */
final class MonitorRepository extends DocumentRepository
{
    /**
     * Wyszukiwanie wystąpień wg filtry UX:
     * - monitoringId (główny filtr), processId (grupa wystąpień),
     * - tags — filtry wielowartościowe (AND: dokument musi mieć wszystkie podane etykiety).
     *
     * @param array<string, mixed> $filters
     *
     * @return list<Monitor>
     */
    public function search(array $filters = []): array
    {
        $criteria = [];

        $monitoringId = $filters['monitoringId'] ?? null;
        if (is_string($monitoringId) && '' !== $monitoringId) {
            $criteria['monitoringId'] = $monitoringId;
        }

        $processId = $filters['processId'] ?? null;
        if (is_string($processId) && '' !== $processId) {
            $criteria['processId'] = $processId;
        }

        $tags = $this->normalizeTags($filters['tags'] ?? null);
        if ([] !== $tags) {
            $criteria['tags'] = ['$all' => $tags];
        }

        /** @var list<Monitor> $monitors */
        $monitors = $this->findBy($criteria, ['createdAt' => 'desc']);

        return $monitors;
    }

    /** @return list<Monitor> */
    public function findByProcessId(string $processId): array
    {
        /** @var list<Monitor> $monitors */
        $monitors = $this->findBy(['processId' => $processId], ['createdAt' => 'desc']);

        return $monitors;
    }

    /**
     * Tagi mogą przyjść jako "a,b,c" (string) albo ["a", "b"] (array z ?tags[]=...).
     *
     * @return list<string>
     */
    private function normalizeTags(mixed $tags): array
    {
        if (is_string($tags) && '' !== $tags) {
            $tags = explode(',', $tags);
        }

        if (!is_array($tags)) {
            return [];
        }

        return array_values(array_filter(
            $tags,
            static fn (mixed $tag): bool => is_string($tag) && '' !== trim($tag),
        ));
    }
}
