<?php

declare(strict_types=1);

namespace App\Attribute\FilterParam;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

/**
 * Dokłada dokumentowane w OpenAPI parametry query (QueryParameter) do operacji
 * kolekcji zasobów, na podstawie klasy filtra wskazanej w extraProperties:
 *   extraProperties: ['filter' => MonitorFilter::class].
 *
 * Pola klasy filtra z #[FilterParam] stają się parametrami; pola bez atrybutu
 * (page, itemsPerPage) dokumentuje sam API Platform z metadanych paginacji.
 * Ręczne parameters: na operacji wygrywają z wyliczonymi (escape hatch).
 */
final class FilterParametersMetadataFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $decorated,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        foreach ($collection as $index => $resource) {
            $operations = $resource->getOperations();

            if (null === $operations) {
                continue;
            }

            $replaced = [];

            foreach ($operations as $name => $operation) {
                if (!$operation instanceof CollectionOperationInterface) {
                    continue;
                }

                $filterClass = $operation->getExtraProperties()['filter'] ?? null;

                if (!is_string($filterClass) || !class_exists($filterClass)) {
                    continue;
                }

                $parameters = $this->parametersFor($filterClass, $operation->getParameters());

                if ([] === $parameters) {
                    continue;
                }

                $replaced[$name] = $operation->withParameters($parameters);
            }

            if ([] !== $replaced) {
                $all = [];

                foreach ($operations as $name => $operation) {
                    $all[$name] = $replaced[$name] ?? $operation;
                }

                $collection[$index] = $resource->withOperations(new Operations($all));
            }
        }

        return $collection;
    }

    /**
     * @return array<string, QueryParameter>
     */
    private function parametersFor(string $filterClass, ?Parameters $existing): array
    {
        \assert(class_exists($filterClass));

        $declared = [];

        if (null !== $existing) {
            foreach ($existing as $key => $parameter) {
                $declared[(string) $key] = $parameter;
            }
        }

        $filterProperties = (new \ReflectionClass($filterClass))->getProperties();
        $parameters = [];

        foreach ($filterProperties as $property) {
            $attributes = $property->getAttributes(FilterParam::class);

            if ([] === $attributes) {
                continue;
            }

            if (array_key_exists($property->getName(), $declared)) {
                continue;
            }

            /** @var FilterParam $attribute */
            $attribute = $attributes[0]->newInstance();

            $parameters[$property->getName()] = new QueryParameter(
                key: $property->getName(),
                schema: $attribute->schema,
                description: $attribute->description,
                required: $attribute->required,
            );
        }

        return $parameters;
    }
}
