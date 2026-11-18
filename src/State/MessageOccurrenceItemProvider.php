<?php

declare(strict_types=1);

namespace App\State;

use App\ApiResource\MessageOccurrence;
use App\Monitoring\MessageOccurrence as MessageOccurrenceAggregate;
use App\Persistence\MessageOccurrenceRepositoryInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

/** Zasób odczytowy: pojedyncze wystąpienie + pola pochodne z agregatu domenowego. */
/** @implements ProviderInterface<\App\ApiResource\MessageOccurrence> */
final class MessageOccurrenceItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly MessageOccurrenceRepositoryInterface $repository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        $domain = $this->repository->get((string) ($uriVariables['occurrenceId'] ?? ''));

        return null === $domain ? null : self::fromDomain($domain);
    }

    public static function fromDomain(MessageOccurrenceAggregate $domain): MessageOccurrence
    {
        return new MessageOccurrence(
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
            firstOccurredAt: $domain->firstOccurredAt(),
            lastOccurredAt: $domain->lastOccurredAt(),
        );
    }
}
