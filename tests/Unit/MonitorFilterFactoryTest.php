<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\ApiResource\Monitor\Dto\MonitorFilter;
use App\ApiResource\Monitor\State\MonitorFilterFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MonitorFilterFactory::class)]
final class MonitorFilterFactoryTest extends TestCase
{
    private MonitorFilterFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new MonitorFilterFactory();
    }

    public function testEmptyContextProducesDefaults(): void
    {
        $filter = $this->factory->fromContext([]);

        self::assertNull($filter->monitoringId);
        self::assertNull($filter->processId);
        self::assertSame([], $filter->tags);
        self::assertSame(1, $filter->page);
        self::assertSame(MonitorFilterFactory::DEFAULT_ITEMS_PER_PAGE, $filter->itemsPerPage);
    }

    public function testTagsFromCommaSeparatedString(): void
    {
        $filter = $this->factory->fromFilters(['tags' => 'billing, urgent,,']);

        self::assertSame(['billing', 'urgent'], $filter->tags);
    }

    public function testTagsFromMultipleQueryParams(): void
    {
        $filter = $this->factory->fromFilters(['tags' => ['billing', 'urgent', '', 5]]);

        self::assertSame(['billing', 'urgent'], $filter->tags);
    }

    public function testBlankStringValuesAreIgnored(): void
    {
        $filter = $this->factory->fromFilters(['monitoringId' => '  ', 'processId' => 'invoiceId-42']);

        self::assertNull($filter->monitoringId);
        self::assertSame('invoiceId-42', $filter->processId);
    }

    public function testNonStringValueIsIgnored(): void
    {
        $filter = $this->factory->fromFilters(['monitoringId' => 42]);

        self::assertNull($filter->monitoringId);
    }

    public function testPageIsClampedToMinimum(): void
    {
        self::assertSame(1, $this->factory->fromFilters(['page' => '0'])->page);
        self::assertSame(1, $this->factory->fromFilters(['page' => -3])->page);
        self::assertSame(7, $this->factory->fromFilters(['page' => '7'])->page);
    }

    public function testItemsPerPageIsClamped(): void
    {
        self::assertSame(1, $this->factory->fromFilters(['itemsPerPage' => 0])->itemsPerPage);
        self::assertSame(MonitorFilterFactory::MAX_ITEMS_PER_PAGE, $this->factory->fromFilters(['itemsPerPage' => 10000])->itemsPerPage);
        self::assertSame(13, $this->factory->fromFilters(['itemsPerPage' => '13'])->itemsPerPage);
    }

    public function testFullQueryIsMapped(): void
    {
        $filter = $this->factory->fromContext(['filters' => [
            'monitoringId' => 'invoiceId-42',
            'processId' => 'spring-2026',
            'tags' => ['billing'],
            'page' => '3',
            'itemsPerPage' => 50,
        ]]);

        self::assertSame('invoiceId-42', $filter->monitoringId);
        self::assertSame('spring-2026', $filter->processId);
        self::assertSame(['billing'], $filter->tags);
        self::assertSame(3, $filter->page);
        self::assertSame(50, $filter->itemsPerPage);
        self::assertInstanceOf(MonitorFilter::class, $filter);
    }
}
