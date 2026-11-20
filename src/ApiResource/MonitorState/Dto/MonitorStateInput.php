<?php

declare(strict_types=1);

namespace App\ApiResource\MonitorState\Dto;

use App\ApiResource\MonitorState\Document\MonitorState;
use App\ApiResource\MonitorState\Document\Stage;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Input dla POST /api/monitors/{occurrenceId}/states (nowy stan) i PUT /api/monitors/{occurrenceId}/states/{id} (pełne nadpisanie).
 * Brakujące/puste pole wymagane = '' → NotBlank → 422.
 */
final class MonitorStateInput
{
    #[Groups(['monitor-state:create', 'monitor-state:update'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: Stage::VALUES)]
    public string $stage = '';

    /** RFC3339, opcjonalnie z ułamkiem sekundy do mikrosekund, np. 2026-11-20T10:00:00.123456+00:00. */
    #[Groups(['monitor-state:create', 'monitor-state:update'])]
    #[Assert\NotBlank]
    public string $occurredAt = '';

    #[Groups(['monitor-state:create', 'monitor-state:update'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $application = '';

    #[Groups(['monitor-state:create', 'monitor-state:update'])]
    #[Assert\Length(max: 255)]
    public ?string $environment = null;

    #[Groups(['monitor-state:create', 'monitor-state:update'])]
    #[Assert\Length(max: 255)]
    public ?string $busName = null;

    #[Groups(['monitor-state:create', 'monitor-state:update'])]
    #[Assert\Length(max: 255)]
    public ?string $transportName = null;

    #[Groups(['monitor-state:create', 'monitor-state:update'])]
    #[Assert\Length(max: 300)]
    public ?string $handlerName = null;

    #[Groups(['monitor-state:create', 'monitor-state:update'])]
    #[Assert\Length(max: 300)]
    public ?string $errorClass = null;

    #[Groups(['monitor-state:create', 'monitor-state:update'])]
    #[Assert\Length(max: 4000)]
    public ?string $errorMessage = null;

    #[Assert\Callback]
    public function validateOccurredAt(ExecutionContextInterface $context): void
    {
        // pusty occurredAt zgłasza już NotBlank
        if ('' !== $this->occurredAt && null === MonitorState::parseOccurredAt($this->occurredAt)) {
            $context->buildViolation(MonitorState::OCCURRED_AT_MESSAGE)->atPath('occurredAt')->addViolation();
        }
    }
}
