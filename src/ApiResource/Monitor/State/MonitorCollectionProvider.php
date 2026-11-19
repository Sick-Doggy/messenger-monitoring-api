<?php

declare(strict_types=1);

namespace App\ApiResource\Monitor\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Monitor\Dto\MonitorOutput;
use App\ApiResource\Monitor\Repository\MonitorRepository;
use App\State\CollectionPaginator;

/**
 * Odczyt kolekcji wystąpień: MonitorFilterFactory → repozytorium → paginator (offset).
 *
 * @implements ProviderInterface<CollectionPaginator<MonitorOutput>>
 */
final class MonitorCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly MonitorRepository $repository,
        private readonly MonitorFilterFactory $filterFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CollectionPaginator
    {
        $filter = $this->filterFactory->fromContext($context);

        $monitors = $this->repository->search($filter);
        $totalItems = $this->repository->countBy($filter);

        $items = array_map(static fn ($monitor): MonitorOutput => MonitorOutput::fromMonitor($monitor), $monitors);

        return new CollectionPaginator($items, $totalItems, $filter->page, $filter->itemsPerPage);
    }
}
