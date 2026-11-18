<?php

declare(strict_types=1);

namespace App\Monitoring;

final class OccurrenceStatus
{
    public function __construct(
        public readonly Stage $stage,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly string $application,
        public readonly ?string $environment = null,
        public readonly ?string $busName = null,
        public readonly ?string $transportName = null,
        public readonly ?string $handlerName = null,
        public readonly ?string $errorClass = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $payload = null,
    ) {
    }

    public function identityKey(): string
    {
        return sprintf('%s|%s|%s', $this->stage->value, $this->occurredAt->format('U.u'), $this->application);
    }
}
