<?php

declare(strict_types=1);

namespace App\ApiResource\MonitorState\Dto;

use ApiPlatform\Metadata\ApiProperty;
use App\ApiResource\MonitorState\Document\MonitorState;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Output dla wszystkich operacji odczytu i zapisu zasobu MonitorState.
 */
final class MonitorStateOutput
{
    #[Groups(['monitor-state:read'])]
    #[ApiProperty(identifier: true)]
    public string $id = '';

    #[Groups(['monitor-state:read'])]
    public string $occurrenceId = '';

    #[Groups(['monitor-state:read'])]
    public string $stage = '';

    #[Groups(['monitor-state:read'])]
    public string $occurredAt = '';

    #[Groups(['monitor-state:read'])]
    public string $application = '';

    #[Groups(['monitor-state:read'])]
    public ?string $environment = null;

    #[Groups(['monitor-state:read'])]
    public ?string $busName = null;

    #[Groups(['monitor-state:read'])]
    public ?string $transportName = null;

    #[Groups(['monitor-state:read'])]
    public ?string $handlerName = null;

    #[Groups(['monitor-state:read'])]
    public ?string $errorClass = null;

    #[Groups(['monitor-state:read'])]
    public ?string $errorMessage = null;

    public static function fromState(MonitorState $state): self
    {
        $output = new self();
        $output->id = $state->getId();
        $output->occurrenceId = $state->getOccurrenceId();
        $output->stage = $state->getStage()->value;
        $output->occurredAt = $state->getOccurredAt();
        $output->application = $state->getApplication();
        $output->environment = $state->getEnvironment();
        $output->busName = $state->getBusName();
        $output->transportName = $state->getTransportName();
        $output->handlerName = $state->getHandlerName();
        $output->errorClass = $state->getErrorClass();
        $output->errorMessage = $state->getErrorMessage();

        return $output;
    }
}
