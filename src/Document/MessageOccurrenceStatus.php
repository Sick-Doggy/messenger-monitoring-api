<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * Wpis zmiany stanu w głowie. occurredAt trzymamy jako string (kontrakt: RFC3339 z mikrosekundami)
 * + occurredAtTs (mikrosekundy epoch) do sortowania — BSON Date przechowuje tylko milisekundy.
 */
#[ODM\EmbeddedDocument]
final class MessageOccurrenceStatus
{
    public function __construct(
        #[ODM\Field(type: 'string')]
        private Stage $stage,
        #[ODM\Field(type: 'string', nullable: false)]
        private string $occurredAt,
        #[ODM\Field(type: 'int', nullable: false)]
        private int $occurredAtTs,
        #[ODM\Field(type: 'string')]
        private string $application,
        #[ODM\Field(type: 'string', nullable: true)]
        private ?string $environment = null,
        #[ODM\Field(type: 'string', nullable: true)]
        private ?string $busName = null,
        #[ODM\Field(type: 'string', nullable: true)]
        private ?string $transportName = null,
        #[ODM\Field(type: 'string', nullable: true)]
        private ?string $handlerName = null,
        #[ODM\Field(type: 'string', nullable: true)]
        private ?string $errorClass = null,
        #[ODM\Field(type: 'string', nullable: true)]
        private ?string $errorMessage = null,
        #[ODM\Field(type: 'string', nullable: true)]
        private ?string $payload = null,
    ) {
    }

    public function getStage(): Stage
    {
        return $this->stage;
    }

    public function getOccurredAt(): string
    {
        return $this->occurredAt;
    }

    public function getOccurredAtTs(): int
    {
        return $this->occurredAtTs;
    }

    public function getApplication(): string
    {
        return $this->application;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function getBusName(): ?string
    {
        return $this->busName;
    }

    public function getTransportName(): ?string
    {
        return $this->transportName;
    }

    public function getHandlerName(): ?string
    {
        return $this->handlerName;
    }

    public function getErrorClass(): ?string
    {
        return $this->errorClass;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getPayload(): ?string
    {
        return $this->payload;
    }
}
