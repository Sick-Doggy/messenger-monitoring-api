<?php

declare(strict_types=1);

namespace Tests\Filter;

use App\Filter\FilterException;
use App\Filter\MessageOccurrenceFilter;
use App\Filter\MessageOccurrenceFilterFactory;
use App\Filter\OccurrenceSortField;
use App\Filter\SortDirection;
use App\Monitoring\Stage;
use PHPUnit\Framework\TestCase;

final class MessageOccurrenceFilterFactoryTest extends TestCase
{
    private MessageOccurrenceFilterFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new MessageOccurrenceFilterFactory();
    }

    public function testDefaults(): void
    {
        $filter = $this->factory->fromQuery([]);

        self::assertNull($filter->className);
        self::assertSame([], $filter->tags);
        self::assertNull($filter->occurredAtFrom);
        self::assertSame(OccurrenceSortField::LastOccurredAt, $filter->sortField);
        self::assertSame(SortDirection::Desc, $filter->sortDirection);
        self::assertSame(1, $filter->page);
        self::assertSame(MessageOccurrenceFilter::DEFAULT_ITEMS_PER_PAGE, $filter->itemsPerPage);
    }

    public function testBuildsFilterFromFullQuery(): void
    {
        $filter = $this->factory->fromQuery([
            'className' => 'App\Message\SendInvoice',
            'monitoringId' => 'INV-123',
            'processId' => 'ORD-99',
            'application' => 'billing',
            'environment' => 'prod',
            'currentStage' => 'failed-retry',
            'tags' => 'billing,invoices',
            'occurredAt[after]' => '2026-11-17T20:00:00.000+01:00',
            'occurredAt[before]' => '2026-11-17T21:00:00+01:00',
            'sort' => '-monitoringId',
            'page' => '2',
            'itemsPerPage' => '50',
        ]);

        self::assertSame('App\Message\SendInvoice', $filter->className);
        self::assertSame('INV-123', $filter->monitoringId);
        self::assertSame('ORD-99', $filter->processId);
        self::assertSame('billing', $filter->application);
        self::assertSame('prod', $filter->environment);
        self::assertSame(Stage::FailedRetry, $filter->currentStage);
        self::assertSame(['billing', 'invoices'], $filter->tags);
        self::assertSame('2026-11-17 20:00:00.000000', $filter->occurredAtFrom?->format('Y-m-d H:i:s.u'));
        self::assertSame('2026-11-17 21:00:00', $filter->occurredAtTo?->format('Y-m-d H:i:s'));
        self::assertSame(OccurrenceSortField::MonitoringId, $filter->sortField);
        self::assertSame(SortDirection::Desc, $filter->sortDirection);
        self::assertSame(2, $filter->page);
        self::assertSame(50, $filter->itemsPerPage);
    }

    public function testAscendingSortWithoutDash(): void
    {
        $filter = $this->factory->fromQuery(['sort' => 'className']);

        self::assertSame(OccurrenceSortField::ClassName, $filter->sortField);
        self::assertSame(SortDirection::Asc, $filter->sortDirection);
    }

    public function testUnknownQueryParameterIsRejected(): void
    {
        $this->expectException(FilterException::class);
        $this->expectExceptionMessage('userName');
        $this->factory->fromQuery(['userName' => 'john']);
    }

    public function testInvalidStageIsRejected(): void
    {
        $this->expectException(FilterException::class);
        $this->factory->fromQuery(['currentStage' => 'failed-unknown']);
    }

    public function testInvalidSortFieldIsRejected(): void
    {
        $this->expectException(FilterException::class);
        $this->factory->fromQuery(['sort' => 'payload']);
    }

    public function testItemsPerPageBelowOneIsRejected(): void
    {
        $this->expectException(FilterException::class);
        $this->factory->fromQuery(['itemsPerPage' => '0']);
    }

    public function testItemsPerPageAboveLimitIsRejected(): void
    {
        $this->expectException(FilterException::class);
        $this->factory->fromQuery(['itemsPerPage' => '101']);
    }

    public function testPageZeroIsRejected(): void
    {
        $this->expectException(FilterException::class);
        $this->factory->fromQuery(['page' => '0']);
    }

    public function testPageMustBeNumeric(): void
    {
        $this->expectException(FilterException::class);
        $this->factory->fromQuery(['page' => 'first']);
    }

    public function testInvalidDateIsRejected(): void
    {
        $this->expectException(FilterException::class);
        $this->factory->fromQuery(['occurredAt[after]' => 'yesterday-ish']);
    }

    public function testTagsAreTrimmedAndDeduplicated(): void
    {
        $filter = $this->factory->fromQuery(['tags' => ' billing , invoices ,, billing ']);

        self::assertSame(['billing', 'invoices'], $filter->tags);
    }

    public function testEmptyValuesFallBackToDefaults(): void
    {
        $filter = $this->factory->fromQuery([
            'className' => '',
            'tags' => '',
            'currentStage' => '',
            'sort' => '',
            'page' => '',
        ]);

        self::assertNull($filter->className);
        self::assertSame([], $filter->tags);
        self::assertNull($filter->currentStage);
        self::assertSame(OccurrenceSortField::LastOccurredAt, $filter->sortField);
        self::assertSame(1, $filter->page);
    }
}
