<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\State\Pagination\PaginatorInterface;

/** Paginacja dla custom providerów (ODM): AP serializuje to jako tablicę + nagłówki Link. */
/**
 * @implements \IteratorAggregate<int, mixed>
 * @implements PaginatorInterface<mixed>
 */
final class DocumentPaginator implements \Countable, \IteratorAggregate, PaginatorInterface
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly float $totalItems,
        private readonly int $currentPage,
        private readonly int $itemsPerPage,
    ) {
    }

    /** @return \Generator<int, mixed> */
    public function getIterator(): \Generator
    {
        foreach ($this->items as $item) {
            yield $item;
        }
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getCurrentPage(): float
    {
        return (float) $this->currentPage;
    }

    public function getLastPage(): float
    {
        return (float) max(1, (int) ceil($this->totalItems / $this->itemsPerPage));
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->itemsPerPage;
    }

    public function getTotalItems(): float
    {
        return $this->totalItems;
    }
}
