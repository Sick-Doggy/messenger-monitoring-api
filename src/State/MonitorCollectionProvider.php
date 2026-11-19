<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use App\Dto\MonitorOutput;
use App\Repository\MonitorRepository;

/**
 * Odczyt kolekcji wystąpień z filtrami:
 * ?monitoringId=..., ?processId=..., ?tags[]=... (lub ?tags=a,b,c, wielowartościowe).
 * Stronicowanie w pamięci — kolekcja jest mała.
 *
 * @implements ProviderInterface<ArrayPaginator>
 */
final class MonitorCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly MonitorRepository $repository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ArrayPaginator
    {
        $monitors = $this->repository->search($this->filters($context));
        $outputs = array_map(static fn ($monitor): MonitorOutput => MonitorOutput::fromMonitor($monitor), $monitors);

        return new ArrayPaginator($outputs, 0, \count($outputs));
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function filters(array $context): array
    {
        $filters = $context['filters'] ?? [];

        return is_array($filters) ? $filters : [];
    }
}
