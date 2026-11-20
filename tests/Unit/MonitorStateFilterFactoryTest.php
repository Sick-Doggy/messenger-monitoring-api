<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\ApiResource\MonitorState\State\MonitorStateFilterFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MonitorStateFilterFactory::class)]
final class MonitorStateFilterFactoryTest extends TestCase
{
    private const string OCCURRENCE_ID = '6b2f4a1e-9c3d-4f0e-8f7a-1b2c3d4e5f60';

    private MonitorStateFilterFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new MonitorStateFilterFactory();
    }

    public function testEmptyContextProducesDefaults(): void
    {
        $filter = $this->factory->fromContext(self::OCCURRENCE_ID, []);

        self::assertSame(self::OCCURRENCE_ID, $filter->occurrenceId);
        self::assertSame([], $filter->stage);
        self::assertNull($filter->application);
        self::assertSame(1, $filter->page);
        self::assertSame(MonitorStateFilterFactory::DEFAULT_ITEMS_PER_PAGE, $filter->itemsPerPage);
    }

    public function testReadsFiltersFromContext(): void
    {
        $filter = $this->factory->fromContext(self::OCCURRENCE_ID, ['filters' => ['stage' => 'sent', 'application' => ' billing ']]);

        self::assertSame(['sent'], $filter->stage);
        self::assertSame('billing', $filter->application);
    }

    public function testStageFromCommaSeparatedString(): void
    {
        $filter = $this->factory->fromFilters(self::OCCURRENCE_ID, ['stage' => 'failed-retry, failed-final,,']);

        self::assertSame(['failed-retry', 'failed-final'], $filter->stage);
    }

    public function testStageFromArrayIsTrimmedAndDeduplicated(): void
    {
        $filter = $this->factory->fromFilters(self::OCCURRENCE_ID, ['stage' => [' sent', 'sent', '', 42, 'handled']]);

        self::assertSame(['sent', 'handled'], $filter->stage);
    }

    public function testBlankApplicationIsIgnored(): void
    {
        self::assertNull($this->factory->fromFilters(self::OCCURRENCE_ID, ['application' => '   '])->application);
        self::assertNull($this->factory->fromFilters(self::OCCURRENCE_ID, ['application' => ['billing']])->application);
    }

    public function testPaginationIsClamped(): void
    {
        self::assertSame(1, $this->factory->fromFilters(self::OCCURRENCE_ID, ['page' => '-3'])->page);
        self::assertSame(1, $this->factory->fromFilters(self::OCCURRENCE_ID, ['page' => 'abc'])->page);
        self::assertSame(4, $this->factory->fromFilters(self::OCCURRENCE_ID, ['page' => '4'])->page);
        self::assertSame(1, $this->factory->fromFilters(self::OCCURRENCE_ID, ['itemsPerPage' => '0'])->itemsPerPage);
        self::assertSame(
            MonitorStateFilterFactory::MAX_ITEMS_PER_PAGE,
            $this->factory->fromFilters(self::OCCURRENCE_ID, ['itemsPerPage' => 10000])->itemsPerPage,
        );
    }
}
