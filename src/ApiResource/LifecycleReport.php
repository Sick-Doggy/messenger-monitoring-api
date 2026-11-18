<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'LifecycleReport',
    description: 'Raport zmiany stanu pojedynczego wystąpienia wiadomości (ingest z biblioteki). Write-only.',
    operations: [
        new Post(
            uriTemplate: '/api/lifecycle-reports',
            input: LifecycleReport::class,
            output: LifecycleReportReceipt::class,
            processor: \App\State\LifecycleReportProcessor::class,
            denormalizationContext: ['groups' => ['lifecycle:write']],
        ),
    ],
)]
final class LifecycleReport
{
    #[Groups(['lifecycle:write'])]
    #[Assert\NotBlank]
    #[Assert\Uuid(strict: false)]
    public ?string $occurrenceId = null;

    #[Groups(['lifecycle:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 191)]
    public ?string $className = null;

    #[Groups(['lifecycle:write'])]
    #[Assert\Length(max: 191)]
    public ?string $monitoringId = null;

    #[Groups(['lifecycle:write'])]
    #[Assert\Length(max: 191)]
    public ?string $processId = null;

    #[Groups(['lifecycle:write'])]
    #[Assert\Length(max: 191)]
    public ?string $name = null;

    /**
     * @var list<string>
     */
    #[Groups(['lifecycle:write'])]
    #[Assert\Type('array')]
    #[Assert\All([new Assert\Type('string')])]
    public array $tags = [];

    /**
     * @var ReportedStatus[]
     */
    #[Groups(['lifecycle:write'])]
    #[Assert\Count(min: 1, minMessage: 'Raport musi zawierać co najmniej jeden stan.')]
    #[Assert\Valid]
    public array $statuses = [];
}
