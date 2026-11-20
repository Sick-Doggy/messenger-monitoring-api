<?php

declare(strict_types=1);

namespace App\ApiResource\MonitorState\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Monitor\Repository\MonitorRepository;
use App\ApiResource\MonitorState\Document\MonitorState;
use App\ApiResource\MonitorState\Dto\MonitorStateOutput;
use App\ApiResource\MonitorState\Repository\MonitorStateRepository;
use App\State\CollectionPaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Odczyt historii stanów wystąpienia (GET /api/monitors/{occurrenceId}/states):
 * MonitorStateFilterFactory → repozytorium → paginator (offset).
 *
 * @implements ProviderInterface<CollectionPaginator<MonitorStateOutput>>
 */
final class MonitorStateCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly MonitorRepository $monitors,
        private readonly MonitorStateRepository $repository,
        private readonly MonitorStateFilterFactory $filterFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CollectionPaginator
    {
        $occurrenceId = (string) ($uriVariables['occurrenceId'] ?? '');

        if (null === $this->monitors->find($occurrenceId)) {
            throw new NotFoundHttpException('Monitor not found.');
        }

        $filter = $this->filterFactory->fromContext($occurrenceId, $context);

        $states = $this->repository->search($filter);
        $totalItems = $this->repository->countBy($filter);

        $items = array_map(static fn (MonitorState $state): MonitorStateOutput => MonitorStateOutput::fromState($state), $states);

        return new CollectionPaginator($items, $totalItems, $filter->page, $filter->itemsPerPage);
    }
}
