<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ReportStatus
{
    public const STAGES = ['sent', 'received', 'handled', 'send-failed', 'failed-retry', 'failed-final'];

    #[Groups(['lifecycle:write'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: self::STAGES, message: 'Nieznane stage.')]
    public ?string $stage = null;

    #[Groups(['lifecycle:write'])]
    #[Assert\NotBlank]
    #[Assert\DateTime('Y-m-d\TH:i:s.uP', message: 'occurredAt nie jest poprawną datą RFC3339 (z mikrosekundami).')]
    public ?string $occurredAt = null;

    #[Groups(['lifecycle:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 191)]
    public ?string $application = null;

    #[Groups(['lifecycle:write'])]
    #[Assert\Length(max: 191)]
    public ?string $environment = null;

    #[Groups(['lifecycle:write'])]
    #[Assert\Length(max: 191)]
    public ?string $busName = null;

    #[Groups(['lifecycle:write'])]
    #[Assert\Length(max: 191)]
    public ?string $transportName = null;

    #[Groups(['lifecycle:write'])]
    #[Assert\Length(max: 191)]
    public ?string $handlerName = null;

    #[Groups(['lifecycle:write'])]
    #[Assert\Length(max: 191)]
    public ?string $errorClass = null;

    #[Groups(['lifecycle:write'])]
    public ?string $errorMessage = null;

    #[Groups(['lifecycle:write'])]
    #[Assert\Callback([self::class, 'validatePayload'])]
    public ?string $payload = null;

    /** payload to JSON string — walidujemy, że jest prawidłowym JSON-em. */
    public static function validatePayload(?string $payload, ExecutionContextInterface $context, mixed $constraintPayload): void
    {
        if (null === $payload) {
            return;
        }

        $decoded = json_decode($payload);

        if (null === $decoded && \JSON_ERROR_NONE !== json_last_error()) {
            $context->buildViolation('payload nie jest prawidłowym JSON-em.')
                ->atPath('payload')
                ->addViolation();
        }
    }
}
