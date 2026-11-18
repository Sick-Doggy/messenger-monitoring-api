<?php

declare(strict_types=1);

namespace App\Filter;

final class FilterException extends \InvalidArgumentException
{
    /** @param list<string> $allowedValues */
    public static function invalidValue(string $parameter, string $value, array $allowedValues = []): self
    {
        return new self(sprintf(
            'Invalid value "%s" for query parameter "%s".%s',
            $value,
            $parameter,
            [] === $allowedValues ? '' : ' Allowed: ' . implode(', ', $allowedValues),
        ));
    }
}
