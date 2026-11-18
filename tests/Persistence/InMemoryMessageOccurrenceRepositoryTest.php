<?php

declare(strict_types=1);

namespace Tests\Persistence;

use App\Filter\MessageOccurrenceFilter;
use App\Filter\MessageOccurrenceFilterFactory;
use App\Monitoring\MessageOccurrence;
use App\Monitoring\OccurrenceStatus;
use App\Monitoring\Stage;
use App\Persistence\InMemoryMessageOccurrenceRepository;
use PHPUnit\Framework\TestCase;

final class InMemoryMessageOccurrenceRepositoryTest extends TestCase
{
    private InMemoryMessageOccurrenceRepository $repository;
    private MessageOccurrenceFilterFactory $factory;

    protected function setUp(): void
    {
        $this->repository = new InMemoryMessageOccurrenceRepository();
        $this->factory = new MessageOccurrenceFilterFactory();

        $this->repository->save(self::occurrence('id-invoice', 'App\Message\SendInvoice', 'INV-1', 'ORD-99', ['billing'], new \DateTimeImmutable('2026-11-17T20:41:03.100+01:00')));
        $this->repository->save(self::occurrence('id-report', 'App\Message\SendReport', 'REP-1', 'ORD-99', ['reports'], new \DateTimeImmutable('2026-11-17T20:35:00.000+01:00')));
        $this->repository->save(self::occurrence('id-old', 'App\Message\SendInvoice', null, null, ['billing', 'archive'], new \DateTimeImmutable('2026-11-16T08:00:00.000+01:00')));
    }

    public function testFindAllSortedByLastActivityDescending(): void
    {
        $page = $this->repository->findByFilter($this->factory->fromQuery([]));

        self::assertSame(3, $page->totalCount);
        self::assertSame(['id-invoice', 'id-report', 'id-old'], array_map(
            static fn (MessageOccurrence $o): string => $o->occurrenceId,
            $page->items,
        ));
    }

    public function testFiltersByClassNameExact(): void
    {
        $page = $this->repository->findByFilter($this->factory->fromQuery(['className' => 'App\Message\SendInvoice']));

        self::assertSame(2, $page->totalCount);
        self::assertSame(['id-invoice', 'id-old'], array_column($page->items, 'occurrenceId'));
    }

    public function testFiltersByMonitoringId(): void
    {
        $page = $this->repository->findByFilter($this->factory->fromQuery(['monitoringId' => 'REP-1']));

        self::assertSame(1, $page->totalCount);
        self::assertSame('id-report', $page->items[0]->occurrenceId);
    }

    public function testFiltersByAnyTag(): void
    {
        $page = $this->repository->findByFilter($this->factory->fromQuery(['tags' => 'reports']));

        self::assertSame(1, $page->totalCount);
        self::assertSame('id-report', $page->items[0]->occurrenceId);
    }

    public function testFiltersByOccurredAtRange(): void
    {
        $page = $this->repository->findByFilter($this->factory->fromQuery([
            'occurredAt[after]' => '2026-11-17T00:00:00+01:00',
            'occurredAt[before]' => '2026-11-17T23:59:59+01:00',
        ]));

        self::assertSame(2, $page->totalCount);
        self::assertSame('id-invoice', $page->items[0]->occurrenceId);
    }

    public function testFiltersByCurrentStageDerivedField(): void
    {
        $report = $this->repository->get('id-report');
        $report?->append(new OccurrenceStatus(Stage::Handled, new \DateTimeImmutable('2026-11-17T20:36:00.000+01:00'), 'billing'));
        if (null !== $report) {
            $this->repository->save($report);
        }

        $page = $this->repository->findByFilter($this->factory->fromQuery(['currentStage' => 'handled']));

        self::assertSame(1, $page->totalCount);
        self::assertSame(Stage::Handled, $page->items[0]->currentStage());

        $failed = $this->repository->findByFilter($this->factory->fromQuery(['currentStage' => 'failed-final']));
        self::assertSame(['id-invoice', 'id-old'], array_column($failed->items, 'occurrenceId'));
    }

    public function testFiltersByApplicationAcrossStatuses(): void
    {
        $occurrence = self::occurrence('id-consumer', 'App\Message\SendInvoice', null, null, [], new \DateTimeImmutable('2026-11-17T20:41:04.000+01:00'));
        $occurrence->append(new OccurrenceStatus(
            Stage::Received,
            new \DateTimeImmutable('2026-11-17T20:41:04.000+01:00'),
            'billing-consumer',
            environment: 'dev',
        ));
        $this->repository->save($occurrence);

        $page = $this->repository->findByFilter($this->factory->fromQuery([
            'application' => 'billing-consumer',
            'environment' => 'dev',
        ]));

        self::assertSame(1, $page->totalCount);
        self::assertSame('id-consumer', $page->items[0]->occurrenceId);
    }

    public function testPagination(): void
    {
        $page1 = $this->repository->findByFilter($this->factory->fromQuery(['itemsPerPage' => '2']));
        $page2 = $this->repository->findByFilter($this->factory->fromQuery(['itemsPerPage' => '2', 'page' => '2']));

        self::assertSame(['id-invoice', 'id-report'], array_column($page1->items, 'occurrenceId'));
        self::assertSame(['id-old'], array_column($page2->items, 'occurrenceId'));
        self::assertSame(3, $page2->totalCount);
        self::assertSame(2, $page2->totalPages());
    }

    public function testSortingByMonitoringIdAscendingPutsNullsLast(): void
    {
        $page = $this->repository->findByFilter($this->factory->fromQuery(['sort' => '+monitoringId']));

        self::assertSame(['INV-1', 'REP-1', null], array_map(
            static fn (MessageOccurrence $o): ?string => $o->monitoringId(),
            $page->items,
        ));
    }

    public function testGetReturnsSavedOccurrence(): void
    {
        self::assertNotNull($this->repository->get('id-invoice'));
        self::assertNull($this->repository->get('missing'));
    }

    /** @param list<string> $tags */
    private static function occurrence(
        string $id,
        string $className,
        ?string $monitoringId,
        ?string $processId,
        array $tags,
        \DateTimeImmutable $occurredAt,
    ): MessageOccurrence {
        $occurrence = new MessageOccurrence($id, $className);
        $occurrence->fillMetadata($monitoringId, $processId, null, $tags);
        $occurrence->append(new OccurrenceStatus(Stage::FailedFinal, $occurredAt, 'billing', environment: 'prod'));

        return $occurrence;
    }
}
