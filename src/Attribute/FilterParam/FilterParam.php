<?php

declare(strict_types=1);

namespace App\Attribute\FilterParam;

/**
 * Deklaracja parametru query dla filtra zasobu. Opisuje się nią pola klasy
 * filtra (np. Dto\MonitorFilter); FilterParametersMetadataFactory wypluwa z nich
 * QueryParameter — czyli dokumentację OpenAPI — i dokłada do operacji kolekcji.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class FilterParam
{
    /**
     * @param array<string, mixed>|null $schema — fragment JSON Schema parametru
     */
    public function __construct(
        public readonly ?string $description = null,
        public readonly ?array $schema = null,
        public readonly ?bool $required = null,
    ) {
    }
}
