<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Monitoring\Stage;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'OccurrenceStatus',
    description: 'Pojedynczy stan w cyklu życia wystąpienia wiadomości.',
    operations: [
        new GetCollection(
            uriTemplate: '/api/message-occurrences/{occurrenceId}/statuses',
            uriVariables: ['occurrenceId' => new Link(fromProperty: 'occurrenceId', identifiers: ['occurrenceId'])],
            provider: \App\State\OccurrenceStatusCollectionProvider::class,
            normalizationContext: ['groups' => ['occurrence_status:read']],
        ),
    ],
)]
final class MessageOccurrenceStatus
{
    public function __construct(
        #[Groups(['occurrence_status:read'])]
        public readonly Stage $stage,
        #[Groups(['occurrence_status:read'])]
        public readonly \DateTimeImmutable $occurredAt,
        #[Groups(['occurrence_status:read'])]
        public readonly string $application,
        #[Groups(['occurrence_status:read'])]
        public readonly ?string $environment = null,
        #[Groups(['occurrence_status:read'])]
        public readonly ?string $busName = null,
        #[Groups(['occurrence_status:read'])]
        public readonly ?string $transportName = null,
        #[Groups(['occurrence_status:read'])]
        public readonly ?string $handlerName = null,
        #[Groups(['occurrence_status:read'])]
        public readonly ?string $errorClass = null,
        #[Groups(['occurrence_status:read'])]
        public readonly ?string $errorMessage = null,
        #[Groups(['occurrence_status:read'])]
        public readonly ?string $payload = null,
    ) {
    }
}
