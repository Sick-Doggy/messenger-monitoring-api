<?php

declare(strict_types=1);

namespace App\ApiResource\Monitor\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input dla PATCH /api/monitors/{occurrenceId}.
 * null/brak pola = bez zmian; tags: [] = wyczyszczenie etykiet.
 * occurrenceId jest kluczem korelacji/upsertu i nie może być zmieniany — nie występuje w PatchInput.
 */
final class MonitorPatchInput
{
    #[Groups(['monitor:patch'])]
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 300)]
    #[Assert\Regex(
        pattern: '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/',
        message: 'className musi być FQCN wiadomości, np. App\Message\SendInvoice.',
    )]
    public ?string $className = null;

    #[Groups(['monitor:patch'])]
    #[Assert\Length(max: 255)]
    public ?string $monitoringId = null;

    #[Groups(['monitor:patch'])]
    #[Assert\Length(max: 255)]
    public ?string $processId = null;

    #[Groups(['monitor:patch'])]
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    /** @var list<string>|null */
    #[Groups(['monitor:patch'])]
    #[Assert\Type('array')]
    #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 100)])]
    public ?array $tags = null;
}
