<?php

declare(strict_types=1);

namespace Tests\Monitoring;

use App\Monitoring\MessageOccurrence;
use App\Monitoring\OccurrenceStatus;
use App\Monitoring\Stage;
use PHPUnit\Framework\TestCase;

final class MessageOccurrenceTest extends TestCase
{
    public function testDerivesAttemptsWithRetries(): void
    {
        $occurrence = $this->occurrenceWithStatuses([
            $this->makeStatus(Stage::Sent, '20:41:03.123'),
            $this->makeStatus(Stage::Received, '20:41:04.500'),
            $this->makeStatus(Stage::FailedRetry, '20:41:05.900'),
            $this->makeStatus(Stage::FailedRetry, '20:41:06.100'),
        ]);

        self::assertSame(3, $occurrence->attempts());
        self::assertSame(Stage::FailedRetry, $occurrence->currentStage());
        self::assertTrue($occurrence->hasPendingRetry());
        self::assertNull($occurrence->finishedAt());
    }

    public function testNoStatusesMeansZeroAttemptsAndUnknownStage(): void
    {
        $occurrence = new MessageOccurrence('id-1', 'App\Message\SendInvoice');

        self::assertSame(0, $occurrence->attempts());
        self::assertNull($occurrence->currentStage());
        self::assertFalse($occurrence->hasPendingRetry());
        self::assertNull($occurrence->finishedAt());
        self::assertNull($occurrence->lastOccurredAt());
    }

    public function testFinishedAfterHandled(): void
    {
        $occurrence = $this->occurrenceWithStatuses([
            $this->makeStatus(Stage::Sent, '20:41:03.123'),
            $this->makeStatus(Stage::Handled, '20:41:09.777'),
        ]);

        self::assertSame(Stage::Handled, $occurrence->currentStage());
        self::assertFalse($occurrence->hasPendingRetry());
        self::assertSame('2026-11-17 20:41:09.777000', $occurrence->finishedAt()?->format('Y-m-d H:i:s.u'));
    }

    public function testFinishedFinalIsTerminalButNotPending(): void
    {
        $occurrence = $this->occurrenceWithStatuses([
            $this->makeStatus(Stage::FailedFinal, '20:41:09.000', errorClass: 'HandlerFailedValidationException'),
        ]);

        self::assertNotNull($occurrence->finishedAt());
        self::assertFalse($occurrence->hasPendingRetry());
    }

    public function testDuplicateStatusIsIgnored(): void
    {
        $occurrence = $this->occurrenceWithStatuses([
            $this->makeStatus(Stage::Sent, '20:41:03.123', transportName: 'async'),
        ]);

        $duplicate = $this->makeStatus(Stage::Sent, '20:41:03.123', transportName: 'async-fast');
        $sameApplication = $this->makeStatus(Stage::Sent, '20:41:03.123', application: 'billing-consumer');

        self::assertFalse($occurrence->append($duplicate));
        self::assertCount(1, $occurrence->statuses());
        self::assertSame('async', $occurrence->statuses()[0]->transportName);

        self::assertTrue($occurrence->append($sameApplication));
        self::assertCount(2, $occurrence->statuses());
    }

    public function testStatusesAreSortedByOccurredAt(): void
    {
        $occurrence = $this->occurrenceWithStatuses([
            $this->makeStatus(Stage::Handled, '20:41:10.000'),
            $this->makeStatus(Stage::Sent, '20:41:03.000'),
            $this->makeStatus(Stage::Received, '20:41:05.000'),
        ]);

        self::assertSame([Stage::Sent, Stage::Received, Stage::Handled], array_map(
            static fn (OccurrenceStatus $status): Stage => $status->stage,
            $occurrence->statuses(),
        ));
        self::assertSame('2026-11-17 20:41:03.000000', $occurrence->firstOccurredAt()?->format('Y-m-d H:i:s.u'));
        self::assertSame('2026-11-17 20:41:10.000000', $occurrence->lastOccurredAt()?->format('Y-m-d H:i:s.u'));
    }

    public function testMetadataIsFilledOnlyOnEmptyFields(): void
    {
        $occurrence = new MessageOccurrence('id-1', 'App\Message\SendInvoice');
        $occurrence->fillMetadata('INV-1', 'ORD-99', 'Wystawienie faktury', ['billing']);

        $occurrence->fillMetadata(null, 'ORD-100', null, ['other-tag']);

        self::assertSame('INV-1', $occurrence->monitoringId());
        self::assertSame('ORD-99', $occurrence->processId());
        self::assertSame('Wystawienie faktury', $occurrence->name());
        self::assertSame(['billing'], $occurrence->tags());
    }

    public function testMetadataFillsWhenInitiallyEmpty(): void
    {
        $occurrence = new MessageOccurrence('id-1', 'App\Message\SendInvoice');
        $occurrence->fillMetadata(null, null, null, []);

        $occurrence->fillMetadata('INV-1', null, null, ['billing']);

        self::assertSame('INV-1', $occurrence->monitoringId());
        self::assertSame(['billing'], $occurrence->tags());
    }

    /** @param list<OccurrenceStatus> $statuses */
    private function occurrenceWithStatuses(array $statuses): MessageOccurrence
    {
        $occurrence = new MessageOccurrence('id-1', 'App\Message\SendInvoice');

        foreach ($statuses as $status) {
            $occurrence->append($status);
        }

        return $occurrence;
    }

    private function makeStatus(
        Stage $stage,
        string $time,
        string $application = 'billing',
        ?string $transportName = null,
        ?string $errorClass = null,
    ): OccurrenceStatus {
        return new OccurrenceStatus(
            $stage,
            new \DateTimeImmutable('2026-11-17 ' . $time . '+01:00'),
            $application,
            transportName: $transportName,
            errorClass: $errorClass,
        );
    }
}
