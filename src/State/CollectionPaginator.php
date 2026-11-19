<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\State\Pagination\PaginatorInterface;

/**
 * Paginator in-memory dla custom providerów (Mongo ODM).
 * AP serializuje metadane (hydra:totalItems, hydra:view z first/next/last)
 * na podstawie PaginatorInterface.
 *
 * @template T of object
 *
 * @implements \IteratorAggregate<T>
 * @implements PaginatorInterface<T>
 */
final class CollectionPaginator implements \Countable, \IteratorAggregate, PaginatorInterface
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly int $totalItems,
        private readonly int $currentPage,
        private readonly int $itemsPerPage,
    ) {
    }

    /** @return \Generator<int, T> */
    public function getIterator(): \Generator
    {
        foreach ($this->items as $item) {
            yield $item;
        }
    }

    public function count(): int
    {
        return \count($this->items);
    }

    public function getCurrentPage(): float
    {
        return (float) $this->currentPage;
    }

    public function getLastPage(): float
    {
        if ($this->itemsPerPage < 1) {
            return 1.0;
        }

        return (float) max(1, (int) ceil($this->totalItems / $this->itemsPerPage));
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->itemsPerPage;
    }

    public function getTotalItems(): float
    {
        return (float) $this->totalItems;
    }
}
