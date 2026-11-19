<?php

declare(strict_types=1);

namespace App\ApiResource\Monitor\Dto;

use App\Attribute\FilterParam\FilterParam;

/**
 * Read-input zasobu Monitor: filtry + paginacja. Martwa struktura (readonly)
 * — parsowanie z requestu robi State\MonitorFilterFactory, mapowanie na
 * kryteria Mongo robi Repository\MonitorRepository, dokumentację OpenAPI
 * generuje FilterParametersMetadataFactory z #[FilterParam].
 *
 * Pola paginacji (page, itemsPerPage) są dokumentowane przez sam API Platform
 * z metadanych paginacji — nie opisuje się ich atrybutem.
 */
final class MonitorFilter
{
    #[FilterParam(
        schema: ['type' => 'string'],
        description: 'Główny filtr UX — id biznesowe wystąpienia.',
    )]
    public readonly ?string $monitoringId;

    #[FilterParam(
        schema: ['type' => 'string'],
        description: 'Grupa wystąpień procesu.',
    )]
    public readonly ?string $processId;

    /**
     * Filtry wielowartościowe; wystąpienie musi mieć wszystkie podane tagi (AND).
     *
     * @var list<string>
     */
    #[FilterParam(
        schema: ['type' => 'array', 'items' => ['type' => 'string']],
        description: 'Filtry wielowartościowe; wystąpienie musi mieć wszystkie podane tagi (AND). ?tags[]=a&tags[]=b lub ?tags=a,b.',
    )]
    public readonly array $tags;

    public readonly int $page;

    public readonly int $itemsPerPage;

    /**
     * @param list<string> $tags
     */
    public function __construct(
        ?string $monitoringId = null,
        ?string $processId = null,
        array $tags = [],
        int $page = 1,
        int $itemsPerPage = 25,
    ) {
        $this->monitoringId = $monitoringId;
        $this->processId = $processId;
        $this->tags = $tags;
        $this->page = $page;
        $this->itemsPerPage = $itemsPerPage;
    }
}
