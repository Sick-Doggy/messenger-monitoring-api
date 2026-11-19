<?php

declare(strict_types=1);

namespace App\ApiResource\Monitor;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\ApiResource\Monitor\Dto\MonitorFilter;
use App\ApiResource\Monitor\Dto\MonitorInput;
use App\ApiResource\Monitor\Dto\MonitorOutput;
use App\ApiResource\Monitor\Dto\MonitorPatchInput;
use App\ApiResource\Monitor\State\CreateMonitorProcessor;
use App\ApiResource\Monitor\State\DeleteMonitorProcessor;
use App\ApiResource\Monitor\State\MonitorCollectionProvider;
use App\ApiResource\Monitor\State\MonitorItemProvider;
use App\ApiResource\Monitor\State\UpdateMonitorProcessor;

/**
 * Identifikator zasobu Monitor. Dane wejściowe/wyjściowe są w App\Dto,
 * logika odczytu w App\State (providerzy), zapisu w App\State (procesory).
 *
 * occurrenceId jest identyfikatorem technicznym wystąpienia (key store) —
 * POST działa jako upsert po tym kluczu.
 */
#[ApiResource(shortName: 'Monitor', operations: [
    new Get(
        input: false,
        output: MonitorOutput::class,
        provider: MonitorItemProvider::class,
        normalizationContext: ['groups' => ['monitor:read']],
    ),
    new GetCollection(
        input: false,
        output: MonitorOutput::class,
        provider: MonitorCollectionProvider::class,
        normalizationContext: ['groups' => ['monitor:read']],
        paginationClientItemsPerPage: true,
        extraProperties: ['filter' => MonitorFilter::class],
    ),
    new Post(
        input: MonitorInput::class,
        output: MonitorOutput::class,
        processor: CreateMonitorProcessor::class,
        normalizationContext: ['groups' => ['monitor:read']],
        denormalizationContext: ['groups' => ['monitor:create']],
        validationContext: ['groups' => ['Default', 'monitor:create']],
    ),
    new Put(
        input: MonitorInput::class,
        output: MonitorOutput::class,
        processor: UpdateMonitorProcessor::class,
        normalizationContext: ['groups' => ['monitor:read']],
        denormalizationContext: ['groups' => ['monitor:update']],
        validationContext: ['groups' => ['Default', 'monitor:update']],
    ),
    new Patch(
        input: MonitorPatchInput::class,
        output: MonitorOutput::class,
        processor: UpdateMonitorProcessor::class,
        normalizationContext: ['groups' => ['monitor:read']],
        denormalizationContext: ['groups' => ['monitor:patch']],
        validationContext: ['groups' => ['Default', 'monitor:patch']],
    ),
    new Delete(
        read: false,
        input: false,
        output: false,
        processor: DeleteMonitorProcessor::class,
    ),
])]
final class Monitor
{
    #[ApiProperty(identifier: true)]
    public string $occurrenceId;
}
