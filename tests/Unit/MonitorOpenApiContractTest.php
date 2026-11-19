<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use App\ApiResource\Monitor\Dto\MonitorFilter;
use App\ApiResource\Monitor\Monitor as MonitorResource;
use App\Attribute\FilterParam\FilterParametersMetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test kontraktu dokumentacji: parametry query z #[FilterParam] na klasie filtra
 * muszą wylądować jako QueryParameter na operacji kolekcji (decorator metadanych),
 * a każdy parametr w Swaggerze ma odpowiadać polu filtra (nie może być rozjazdu).
 */
#[CoversClass(FilterParametersMetadataFactory::class)]
final class MonitorOpenApiContractTest extends TestCase
{
    public function testFilterParametersAreInjectedFromFilterModel(): void
    {
        $collectionOperation = $this->collectionOperation();
        $parameters = $collectionOperation->getParameters();

        self::assertNotNull($parameters, 'Decorator ma dopisać parameters z #[FilterParam].');

        $documented = array_keys(iterator_to_array($parameters, true));
        $filterFields = array_values(array_diff($this->filterProperties(), ['page', 'itemsPerPage']));
        sort($filterFields);
        $sortedDocumented = $documented;
        sort($sortedDocumented);

        self::assertSame($filterFields, $sortedDocumented, 'Rozjazd: pola MonitorFilter vs parametry na operacji kolekcji.');
    }

    public function testInjectedParametersAreQueryParametersWithSchema(): void
    {
        $parameters = $this->collectionOperation()->getParameters();

        self::assertNotNull($parameters);
        foreach ($parameters as $parameter) {
            self::assertInstanceOf(QueryParameter::class, $parameter);
            self::assertNotEmpty($parameter->getSchema(), sprintf('Parametr %s bez schema — pojawi się bez typu w OpenAPI.', $parameter->getKey() ?? '?'));
        }
    }

    /** @return list<string> */
    private function filterProperties(): array
    {
        return array_map(
            static fn ($property): string => $property->getName(),
            (new \ReflectionClass(MonitorFilter::class))->getProperties(),
        );
    }

    private function collectionOperation(): Operation
    {
        $attributes = (new \ReflectionClass(MonitorResource::class))->getAttributes(ApiResource::class);
        self::assertNotSame([], $attributes, 'Brak atrybutu #[ApiResource] na Monitor.');

        $resource = $attributes[0]->newInstance();
        $inner = new class($resource) implements ResourceMetadataCollectionFactoryInterface {
            public function __construct(
                private readonly ApiResource $resource,
            ) {
            }

            public function create(string $resourceClass): ResourceMetadataCollection
            {
                return new ResourceMetadataCollection($resourceClass, [$this->resource]);
            }
        };

        $collection = (new FilterParametersMetadataFactory($inner))->create(MonitorResource::class);

        foreach ($collection as $resourceMetadata) {
            $operations = $resourceMetadata->getOperations();

            if (null === $operations) {
                continue;
            }

            foreach ($operations as $operation) {
                if ($operation instanceof GetCollection) {
                    self::assertSame(
                        MonitorFilter::class,
                        $operation->getExtraProperties()['filter'] ?? null,
                        'GetCollection ma wskazywać klasę filtra przez extraProperties.',
                    );

                    return $operation;
                }
            }
        }

        self::fail('Brak operacji GetCollection na zasobie Monitor.');
    }
}
