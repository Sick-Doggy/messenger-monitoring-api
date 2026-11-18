<?php

declare(strict_types=1);

namespace App\Filter;

enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
