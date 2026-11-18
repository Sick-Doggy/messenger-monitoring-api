<?php

declare(strict_types=1);

namespace App\Filter;

enum OccurrenceSortField: string
{
    case LastOccurredAt = 'lastOccurredAt';
    case ClassName = 'className';
    case MonitoringId = 'monitoringId';
}
