<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(
    shortName: 'MessageOccurrencePage',
    description: 'Paginowana strona listy wystąpień wiadomości.',
    operations: [
        new GetCollection(
            uriTemplate: '/api/message-occurrences',
            provider: \App\State\MessageOccurrenceCollectionProvider::class,
        ),
    ],
)]
final class MessageOccurrencePage
{
    /** @param list<MessageOccurrenceSummary> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $totalItems,
        public readonly int $page,
        public readonly int $itemsPerPage,
        public readonly int $totalPages,
    ) {
    }
}
