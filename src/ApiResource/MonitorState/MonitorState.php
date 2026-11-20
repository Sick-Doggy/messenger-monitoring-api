<?php

declare(strict_types=1);

namespace App\ApiResource\MonitorState;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\ApiResource\Monitor\Monitor;
use App\ApiResource\MonitorState\Dto\MonitorStateFilter;
use App\ApiResource\MonitorState\Dto\MonitorStateInput;
use App\ApiResource\MonitorState\Dto\MonitorStateOutput;
use App\ApiResource\MonitorState\Dto\MonitorStatePatchInput;
use App\ApiResource\MonitorState\State\CreateMonitorStateProcessor;
use App\ApiResource\MonitorState\State\DeleteMonitorStateProcessor;
use App\ApiResource\MonitorState\State\MonitorStateCollectionProvider;
use App\ApiResource\MonitorState\State\MonitorStateItemProvider;
use App\ApiResource\MonitorState\State\UpdateMonitorStateProcessor;

/**
 * Identyfikator zasobu MonitorState — stan w historii cyklu życia wystąpienia (Monitor).
 * Dane wejściowe/wyjściowe są w Dto, odczyt w State (providerzy), zapis w State (procesory).
 *
 * Zasób zagnieżdżony pod Monitorem: /api/monitors/{occurrenceId}/states[/{id}].
 * Stan innego wystąpienia niż w URI jest traktowany jak nieistniejący (404).
 */
#[ApiResource(
    shortName: 'MonitorState',
    uriVariables: [
        'occurrenceId' => new Link(fromClass: Monitor::class, identifiers: ['occurrenceId']),
        'id' => new Link(fromClass: self::class, identifiers: ['id']),
    ],
    normalizationContext: ['groups' => ['monitor-state:read']],
    operations: [
        new GetCollection(
            uriTemplate: '/monitors/{occurrenceId}/states',
            uriVariables: ['occurrenceId' => new Link(fromClass: Monitor::class, identifiers: ['occurrenceId'])],
            input: false,
            output: MonitorStateOutput::class,
            provider: MonitorStateCollectionProvider::class,
            paginationClientItemsPerPage: true,
            extraProperties: ['filter' => MonitorStateFilter::class],
            openapi: new OpenApiOperation(summary: 'Historia stanów wystąpienia, chronologicznie wg occurredAt.'),
        ),
        new Post(
            uriTemplate: '/monitors/{occurrenceId}/states',
            uriVariables: ['occurrenceId' => new Link(fromClass: Monitor::class, identifiers: ['occurrenceId'])],
            read: false,
            input: MonitorStateInput::class,
            output: MonitorStateOutput::class,
            processor: CreateMonitorStateProcessor::class,
            denormalizationContext: ['groups' => ['monitor-state:create']],
            validationContext: ['groups' => ['Default']],
            openapi: new OpenApiOperation(
                summary: 'Dopisuje stan do historii wystąpienia.',
                description: 'Idempotentne: ponowne zgłoszenie tego samego stanu (stage + occurredAt + application) zwraca istniejący wpis z tym samym id.',
            ),
        ),
        new Get(
            uriTemplate: '/monitors/{occurrenceId}/states/{id}',
            input: false,
            output: MonitorStateOutput::class,
            provider: MonitorStateItemProvider::class,
        ),
        new Put(
            uriTemplate: '/monitors/{occurrenceId}/states/{id}',
            read: false,
            input: MonitorStateInput::class,
            output: MonitorStateOutput::class,
            processor: UpdateMonitorStateProcessor::class,
            denormalizationContext: ['groups' => ['monitor-state:update']],
            validationContext: ['groups' => ['Default']],
        ),
        new Patch(
            uriTemplate: '/monitors/{occurrenceId}/states/{id}',
            read: false,
            input: MonitorStatePatchInput::class,
            output: MonitorStateOutput::class,
            processor: UpdateMonitorStateProcessor::class,
            denormalizationContext: ['groups' => ['monitor-state:patch']],
            validationContext: ['groups' => ['Default']],
        ),
        new Delete(
            uriTemplate: '/monitors/{occurrenceId}/states/{id}',
            read: false,
            input: false,
            output: false,
            processor: DeleteMonitorStateProcessor::class,
        ),
    ],
)]
final class MonitorState
{
    #[ApiProperty(identifier: true)]
    public string $id;
}
