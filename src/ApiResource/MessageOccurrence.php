<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Monitoring\Stage;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'MessageOccurrence',
    description: 'Nagłówek pojedynczego wystąpienia monitorowanej wiadomości + pola pochodne.',
    operations: [
        new Get(
            uriTemplate: '/api/message-occurrences/{occurrenceId}',
            uriVariables: ['occurrenceId' => new Link(fromProperty: 'occurrenceId', identifiers: ['occurrenceId'])],
            provider: \App\State\MessageOccurrenceItemProvider::class,
            normalizationContext: ['groups' => ['message_occurrence:read']],
        ),
    ],
)]
final class MessageOccurrence
{
    public function __construct(
        #[Groups(['message_occurrence:read', 'message_occurrence:summary'])]
        public readonly string $occurrenceId,
        #[Groups(['message_occurrence:read', 'message_occurrence:summary'])]
        public readonly string $className,
        #[Groups(['message_occurrence:read', 'message_occurrence:summary'])]
        public readonly ?string $monitoringId,
        #[Groups(['message_occurrence:read', 'message_occurrence:summary'])]
        public readonly ?string $processId,
        #[Groups(['message_occurrence:read', 'message_occurrence:summary'])]
        public readonly ?string $name,
        /** @var list<string> */
        #[Groups(['message_occurrence:read', 'message_occurrence:summary'])]
        public readonly array $tags,
        #[Groups(['message_occurrence:read', 'message_occurrence:summary'])]
        public readonly ?Stage $currentStage,
        #[Groups(['message_occurrence:read', 'message_occurrence:summary'])]
        public readonly int $attempts,
        #[Groups(['message_occurrence:read', 'message_occurrence:summary'])]
        public readonly ?\DateTimeImmutable $finishedAt,
        #[Groups(['message_occurrence:read', 'message_occurrence:summary'])]
        public readonly bool $hasPendingRetry,
        #[Groups(['message_occurrence:read'])]
        public readonly ?\DateTimeImmutable $firstOccurredAt,
        #[Groups(['message_occurrence:read', 'message_occurrence:summary'])]
        public readonly ?\DateTimeImmutable $lastOccurredAt,
    ) {
    }
}
