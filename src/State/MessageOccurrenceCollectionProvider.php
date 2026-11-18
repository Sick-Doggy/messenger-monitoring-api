<?php

declare(strict_types=1);

namespace App\State;

use App\ApiResource\MessageOccurrencePage;
use App\ApiResource\MessageOccurrenceSummary;
use App\Filter\FilterException;
use App\Filter\MessageOccurrenceFilterFactory;
use App\Persistence\MessageOccurrenceRepositoryInterface;
use App\Monitoring\MessageOccurrence;
use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use ApiPlatform\State\ProviderInterface;

/** Zasób odczytowy: paginowana lista wystąpień; filtry buduje MessageOccurrenceFilterFactory. */
/** @implements ProviderInterface<\App\ApiResource\MessageOccurrencePage> */
final class MessageOccurrenceCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly MessageOccurrenceRepositoryInterface $repository,
        private readonly MessageOccurrenceFilterFactory $filterFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MessageOccurrencePage
    {
        try {
            $filter = $this->filterFactory->fromQuery($context['filters'] ?? []);
        } catch (FilterException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }
        $page = $this->repository->findByFilter($filter);

        $items = array_map($this->toSummary(...), $page->items);

        return new MessageOccurrencePage($items, $page->totalCount, $page->page, $page->itemsPerPage, $page->totalPages());
    }

    private function toSummary(MessageOccurrence $domain): MessageOccurrenceSummary
    {
        return new MessageOccurrenceSummary(
            occurrenceId: $domain->occurrenceId,
            className: $domain->className,
            monitoringId: $domain->monitoringId(),
            processId: $domain->processId(),
            name: $domain->name(),
            tags: $domain->tags(),
            currentStage: $domain->currentStage(),
            attempts: $domain->attempts(),
            finishedAt: $domain->finishedAt(),
            hasPendingRetry: $domain->hasPendingRetry(),
            lastOccurredAt: $domain->lastOccurredAt(),
        );
    }
}
