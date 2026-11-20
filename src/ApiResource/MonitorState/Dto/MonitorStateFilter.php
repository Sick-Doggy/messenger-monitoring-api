<?php

declare(strict_types=1);

namespace App\ApiResource\MonitorState\Dto;

use App\ApiResource\MonitorState\Document\Stage;
use App\Attribute\FilterParam\FilterParam;

/**
 * Read-input zasobu MonitorState: filtry + paginacja historii jednego wystąpienia.
 * Martwa struktura (readonly) — parsowanie z requestu robi State\MonitorStateFilterFactory,
 * mapowanie na kryteria Mongo robi Repository\MonitorStateRepository, dokumentację OpenAPI
 * generuje FilterParametersMetadataFactory z #[FilterParam].
 *
 * occurrenceId pochodzi z URI (/api/monitors/{occurrenceId}/states), nie z query — bez #[FilterParam].
 * Pola paginacji (page, itemsPerPage) dokumentuje sam API Platform z metadanych paginacji.
 */
final class MonitorStateFilter
{
    public readonly string $occurrenceId;

    /**
     * Filtr wielowartościowy; stan musi mieć jeden z podanych etapów (OR).
     *
     * @var list<string>
     */
    #[FilterParam(
        schema: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => Stage::VALUES]],
        description: 'Etap stanu; wiele wartości = dowolny z nich (OR). ?stage[]=failed-retry&stage[]=failed-final lub ?stage=failed-retry,failed-final.',
    )]
    public readonly array $stage;

    #[FilterParam(
        schema: ['type' => 'string'],
        description: 'Aplikacja, która zgłosiła stan.',
    )]
    public readonly ?string $application;

    public readonly int $page;

    public readonly int $itemsPerPage;

    /**
     * @param list<string> $stage
     */
    public function __construct(
        string $occurrenceId,
        array $stage = [],
        ?string $application = null,
        int $page = 1,
        int $itemsPerPage = 25,
    ) {
        $this->occurrenceId = $occurrenceId;
        $this->stage = $stage;
        $this->application = $application;
        $this->page = $page;
        $this->itemsPerPage = $itemsPerPage;
    }
}
