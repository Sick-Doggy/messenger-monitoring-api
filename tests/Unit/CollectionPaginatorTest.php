<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use ApiPlatform\State\Pagination\PaginatorInterface;
use App\State\CollectionPaginator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CollectionPaginator::class)]
final class CollectionPaginatorTest extends TestCase
{
    public function testExposesPaginationMetadata(): void
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $c = new \stdClass();
        $a->label = 'a';
        $b->label = 'b';
        $c->label = 'c';

        $paginator = new CollectionPaginator([$a, $b, $c], 138, 2, 25);

        self::assertInstanceOf(PaginatorInterface::class, $paginator);
        self::assertSame(2.0, $paginator->getCurrentPage());
        self::assertSame(6.0, $paginator->getLastPage());
        self::assertSame(25.0, $paginator->getItemsPerPage());
        self::assertSame(138.0, $paginator->getTotalItems());
        self::assertCount(3, $paginator);
        self::assertSame([$a, $b, $c], iterator_to_array($paginator->getIterator(), false));
    }
}
