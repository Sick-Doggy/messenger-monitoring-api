<?php

declare(strict_types=1);

namespace App\State;

use App\ApiResource\MessageOccurrenceStatus;
use App\Persistence\MessageOccurrenceRepositoryInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

/** Zasób odczytowy: sub-kolekcja stanów wystąpienia, posortowana po occurredAt ASC. */
/** @implements ProviderInterface<\App\ApiResource\MessageOccurrenceStatus> */
final class OccurrenceStatusCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly MessageOccurrenceRepositoryInterface $repository,
    ) {
    }

    /** @return list<MessageOccurrenceStatus> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $domain = $this->repository->get((string) ($uriVariables['occurrenceId'] ?? ''));

        return null === $domain ? [] : array_map($this->toStatus(...), $domain->statuses());
    }

    private function toStatus(\App\Monitoring\OccurrenceStatus $status): MessageOccurrenceStatus
    {
        return new MessageOccurrenceStatus(
            stage: $status->stage,
            occurredAt: $status->occurredAt,
            application: $status->application,
            environment: $status->environment,
            busName: $status->busName,
            transportName: $status->transportName,
            handlerName: $status->handlerName,
            errorClass: $status->errorClass,
            errorMessage: $status->errorMessage,
            payload: $status->payload,
        );
    }
}
