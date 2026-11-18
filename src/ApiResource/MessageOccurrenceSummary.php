<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Monitoring\Stage;

/** Skrócona reprezentacja wystąpienia na liście (bez pełnych metadanych stanów). */
final class MessageOccurrenceSummary
{
    public function __construct(
        public readonly string $occurrenceId,
        public readonly string $className,
        public readonly ?string $monitoringId,
        public readonly ?string $processId,
        public readonly ?string $name,
        /** @var list<string> */
        public readonly array $tags,
        public readonly ?Stage $currentStage,
        public readonly int $attempts,
        public readonly ?\DateTimeImmutable $finishedAt,
        public readonly bool $hasPendingRetry,
        public readonly ?\DateTimeImmutable $lastOccurredAt,
    ) {
    }
}
