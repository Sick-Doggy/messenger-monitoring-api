<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;

/** Potwierdzenie przyjęcia raportu. */
final class LifecycleReportReceipt
{
    public function __construct(
        #[Groups(['lifecycle:read'])]
        public readonly string $occurrenceId,
        #[Groups(['lifecycle:read'])]
        public readonly bool $created,
        #[Groups(['lifecycle:read'])]
        public readonly int $acceptedStates,
        #[Groups(['lifecycle:read'])]
        public readonly int $skippedDuplicates,
    ) {
    }
}
