<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;

/** Stan wystąpienia w kontrakcie odczytu. */
final class MessageOccurrenceStatus
{
    public function __construct(
        #[Groups(['occurrence:read'])]
        public readonly string $stage,
        #[Groups(['occurrence:read'])]
        public readonly string $occurredAt,
        #[Groups(['occurrence:read'])]
        public readonly string $application,
        #[Groups(['occurrence:read'])]
        public readonly ?string $environment,
        #[Groups(['occurrence:read'])]
        public readonly ?string $busName,
        #[Groups(['occurrence:read'])]
        public readonly ?string $transportName,
        #[Groups(['occurrence:read'])]
        public readonly ?string $handlerName,
        #[Groups(['occurrence:read'])]
        public readonly ?string $errorClass,
        #[Groups(['occurrence:read'])]
        public readonly ?string $errorMessage,
        #[Groups(['occurrence:read'])]
        public readonly ?string $payload,
    ) {
    }
}
