<?php

declare(strict_types=1);

namespace App\ApiResource\MonitorState\Dto;

use App\ApiResource\MonitorState\Document\MonitorState;
use App\ApiResource\MonitorState\Document\Stage;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Input dla PATCH /api/monitors/{occurrenceId}/states/{id}.
 * null/brak pola = bez zmian. id i occurrenceId nie są zmienialne — nie występują w PatchInput.
 */
final class MonitorStatePatchInput
{
    #[Groups(['monitor-state:patch'])]
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Choice(choices: Stage::VALUES)]
    public ?string $stage = null;

    #[Groups(['monitor-state:patch'])]
    #[Assert\NotBlank(allowNull: true)]
    public ?string $occurredAt = null;

    #[Groups(['monitor-state:patch'])]
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 255)]
    public ?string $application = null;

    #[Groups(['monitor-state:patch'])]
    #[Assert\Length(max: 255)]
    public ?string $environment = null;

    #[Groups(['monitor-state:patch'])]
    #[Assert\Length(max: 255)]
    public ?string $busName = null;

    #[Groups(['monitor-state:patch'])]
    #[Assert\Length(max: 255)]
    public ?string $transportName = null;

    #[Groups(['monitor-state:patch'])]
    #[Assert\Length(max: 300)]
    public ?string $handlerName = null;

    #[Groups(['monitor-state:patch'])]
    #[Assert\Length(max: 300)]
    public ?string $errorClass = null;

    #[Groups(['monitor-state:patch'])]
    #[Assert\Length(max: 4000)]
    public ?string $errorMessage = null;

    #[Assert\Callback]
    public function validateOccurredAt(ExecutionContextInterface $context): void
    {
        // null = bez zmiany, pusty string zgłasza już NotBlank
        if (null !== $this->occurredAt && '' !== $this->occurredAt && null === MonitorState::parseOccurredAt($this->occurredAt)) {
            $context->buildViolation(MonitorState::OCCURRED_AT_MESSAGE)->atPath('occurredAt')->addViolation();
        }
    }
}
