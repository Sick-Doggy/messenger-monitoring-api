<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use Symfony\Component\Serializer\Attribute\Groups;

/** Kontrakt odczytu główki (Document pozostaje czysto MongoDB). */
#[ApiResource(
    shortName: 'MessageOccurrence',
    description: 'Główka: jedno wystąpienie monitorowanej wiadomości + cykl życia (stany).',
    operations: [
        new GetCollection(
            uriTemplate: '/api/message-occurrences',
            provider: \App\State\MessageOccurrenceProvider::class,
            normalizationContext: ['groups' => ['occurrence:list']],
        ),
        new Get(
            uriTemplate: '/api/message-occurrences/{occurrenceId}',
            uriVariables: ['occurrenceId' => new Link(fromProperty: 'occurrenceId', identifiers: ['occurrenceId'])],
            provider: \App\State\MessageOccurrenceProvider::class,
            normalizationContext: ['groups' => ['occurrence:read']],
        ),
    ],
)]
final class MessageOccurrence
{
    public function __construct(
        #[Groups(['occurrence:list', 'occurrence:read'])]
        public readonly string $occurrenceId,
        #[Groups(['occurrence:list', 'occurrence:read'])]
        public readonly string $className,
        #[Groups(['occurrence:list', 'occurrence:read'])]
        public readonly ?string $monitoringId,
        #[Groups(['occurrence:list', 'occurrence:read'])]
        public readonly ?string $processId,
        #[Groups(['occurrence:list', 'occurrence:read'])]
        public readonly ?string $name,
        /** @var list<string> */
        #[Groups(['occurrence:list', 'occurrence:read'])]
        public readonly array $tags,
        #[Groups(['occurrence:list', 'occurrence:read'])]
        public readonly ?string $currentStage,
        #[Groups(['occurrence:list', 'occurrence:read'])]
        public readonly int $attempts,
        #[Groups(['occurrence:list', 'occurrence:read'])]
        public readonly ?string $finishedAt,
        #[Groups(['occurrence:list', 'occurrence:read'])]
        public readonly bool $hasPendingRetry,
        #[Groups(['occurrence:list', 'occurrence:read'])]
        public readonly ?string $firstOccurredAt,
        #[Groups(['occurrence:list', 'occurrence:read'])]
        public readonly ?string $lastOccurredAt,
        /** @var list<MessageOccurrenceStatus> */
        #[Groups(['occurrence:read'])]
        public readonly array $statuses = [],
    ) {
    }
}
