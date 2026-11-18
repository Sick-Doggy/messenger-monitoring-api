<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MessageOccurrence;
use App\Document\MessageOccurrence as MessageOccurrenceDocument;
use App\Document\MessageOccurrenceStatus;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Query\Query;

/** Odczyt kontraktowy główki: mapowanie Document → DTO + paginacja po lastOccurredAtTs (najnowsze najpierw). */
/** @implements ProviderInterface<\App\ApiResource\MessageOccurrence> */
final class MessageOccurrenceProvider implements ProviderInterface
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_ITEMS_PER_PAGE = 25;
    private const MAX_ITEMS_PER_PAGE = 100;

    public function __construct(
        private readonly DocumentManager $documentManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MessageOccurrence|DocumentPaginator|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->collection($context['filters'] ?? []);
        }

        $document = $this->documentManager->find(MessageOccurrenceDocument::class, (string) ($uriVariables['occurrenceId'] ?? ''));

        return null === $document ? null : self::toDto($document);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function collection(array $filters): DocumentPaginator
    {
        $page = self::page($filters);
        $itemsPerPage = self::itemsPerPage($filters);

        $builder = $this->documentManager->createQueryBuilder(MessageOccurrenceDocument::class);

        /** @var int $countResult */
        $countResult = $builder->count()->getQuery()->execute();

        /** @var list<MessageOccurrenceDocument> $documents */
        $documents = $builder
            ->sort('lastOccurredAtTs', 'desc')
            ->skip(($page - 1) * $itemsPerPage)
            ->limit($itemsPerPage)
            ->getQuery()
            ->execute();

        $items = [];

        foreach ($documents as $document) {
            $items[] = self::toDto($document);
        }

        return new DocumentPaginator($items, (float) $countResult, $page, $itemsPerPage);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private static function page(array $filters): int
    {
        $page = $filters['page'] ?? self::DEFAULT_PAGE;

        return max(1, is_numeric($page) ? (int) $page : self::DEFAULT_PAGE);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private static function itemsPerPage(array $filters): int
    {
        $itemsPerPage = $filters['itemsPerPage'] ?? self::DEFAULT_ITEMS_PER_PAGE;

        return min(self::MAX_ITEMS_PER_PAGE, max(1, is_numeric($itemsPerPage) ? (int) $itemsPerPage : self::DEFAULT_ITEMS_PER_PAGE));
    }

    public static function toDto(MessageOccurrenceDocument $document): MessageOccurrence
    {
        $statuses = [];

        foreach ($document->getStatuses() as $status) {
            $statuses[] = self::toStatusDto($status);
        }

        return new MessageOccurrence(
            occurrenceId: $document->getId(),
            className: $document->getClassName(),
            monitoringId: $document->getMonitoringId(),
            processId: $document->getProcessId(),
            name: $document->getName(),
            tags: $document->getTags(),
            currentStage: $document->getCurrentStage()?->value,
            attempts: $document->getAttempts(),
            finishedAt: $document->getFinishedAt(),
            hasPendingRetry: $document->getHasPendingRetry(),
            firstOccurredAt: $document->getFirstOccurredAt(),
            lastOccurredAt: $document->getLastOccurredAt(),
            statuses: $statuses,
        );
    }

    public static function toStatusDto(MessageOccurrenceStatus $status): \App\ApiResource\MessageOccurrenceStatus
    {
        return new \App\ApiResource\MessageOccurrenceStatus(
            stage: $status->getStage()->value,
            occurredAt: $status->getOccurredAt(),
            application: $status->getApplication(),
            environment: $status->getEnvironment(),
            busName: $status->getBusName(),
            transportName: $status->getTransportName(),
            handlerName: $status->getHandlerName(),
            errorClass: $status->getErrorClass(),
            errorMessage: $status->getErrorMessage(),
            payload: $status->getPayload(),
        );
    }
}
