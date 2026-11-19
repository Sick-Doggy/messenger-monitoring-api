<?php

declare(strict_types=1);

namespace App\ApiResource\Monitor\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Uuid;

/**
 * Input dla POST /api/monitors (upsert po occurrenceId) i PUT /api/monitors/{occurrenceId}.
 * Brakujące/puste pole = '' → NotBlank → 422.
 */
final class MonitorInput
{
    #[Groups(['monitor:create', 'monitor:update'])]
    #[Assert\NotBlank]
    #[Uuid(versions: [Uuid::V4_RANDOM])]
    public string $occurrenceId = '';

    #[Groups(['monitor:create', 'monitor:update'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 300)]
    #[Assert\Regex(
        pattern: '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/',
        message: 'className musi być FQCN wiadomości, np. App\Message\SendInvoice.',
    )]
    public string $className = '';

    #[Groups(['monitor:create', 'monitor:update'])]
    #[Assert\Length(max: 255)]
    public ?string $monitoringId = null;

    #[Groups(['monitor:create', 'monitor:update'])]
    #[Assert\Length(max: 255)]
    public ?string $processId = null;

    #[Groups(['monitor:create', 'monitor:update'])]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    /**
     * Pusta lista (lub brak pola) = brak etykiet.
     *
     * @var list<string>
     */
    #[Groups(['monitor:create', 'monitor:update'])]
    #[Assert\Type('array')]
    #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 100)])]
    public array $tags = [];
}
