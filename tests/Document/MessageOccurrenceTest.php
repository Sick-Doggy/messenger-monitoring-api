<?php

declare(strict_types=1);

namespace App\Tests\Document;

use App\Document\MessageOccurrence;
use App\Document\MessageOccurrenceStatus;
use App\Document\Stage;
use PHPUnit\Framework\TestCase;

final class MessageOccurrenceTest extends TestCase
{
    private const OCCURRENCE_ID = '8b4e8ee1-2952-49aa-bddc-3a909b933185';

    public function testAppendStatusDeduplicatesByIdentityKey(): void
    {
        $occurrence = $this->occurrence();
        $status = $this->makeStatus(Stage::Sent, '2026-11-17T20:41:03.123+01:00', 'billing');

        self::assertTrue($occurrence->appendStatus($status));
        self::assertFalse($occurrence->appendStatus($this->makeStatus(Stage::Sent, '2026-11-17T20:41:03.123+01:00', 'billing')));
        self::assertCount(1, $occurrence->getStatuses());
        self::assertTrue($occurrence->appendStatus($this->makeStatus(Stage::Sent, '2026-11-17T20:41:03.123+01:00', 'consumer-app')));
        self::assertCount(2, $occurrence->getStatuses());
    }

    public function testStatusesAreSortedByOccurredAt(): void
    {
        $occurrence = $this->occurrence();
        $occurrence->appendStatus($this->makeStatus(Stage::Handled, '2026-11-17T20:41:10.000+01:00', 'billing'));
        $occurrence->appendStatus($this->makeStatus(Stage::Sent, '2026-11-17T20:41:03.000+01:00', 'billing'));
        $occurrence->appendStatus($this->makeStatus(Stage::Received, '2026-11-17T20:41:05.000+01:00', 'billing'));

        self::assertSame([Stage::Sent, Stage::Received, Stage::Handled], array_map(
            static fn (MessageOccurrenceStatus $s): Stage => $s->getStage(),
            $occurrence->getStatuses(),
        ));
    }

    public function testDerivedFields(): void
    {
        $occurrence = $this->occurrence();
        $occurrence->appendStatus($this->makeStatus(Stage::Sent, '2026-11-17T20:41:03.000+01:00', 'billing'));
        $occurrence->appendStatus($this->makeStatus(Stage::FailedRetry, '2026-11-17T20:41:06.000+01:00', 'consumer-app'));
        $occurrence->appendStatus($this->makeStatus(Stage::FailedRetry, '2026-11-17T20:41:07.000+01:00', 'consumer-app'));

        self::assertSame(Stage::FailedRetry, $occurrence->getCurrentStage());
        self::assertSame(3, $occurrence->getAttempts());
        self::assertTrue($occurrence->getHasPendingRetry());
        self::assertNull($occurrence->getFinishedAt());
        self::assertSame('2026-11-17T20:41:03.000+01:00', $occurrence->getFirstOccurredAt());
        self::assertSame('2026-11-17T20:41:07.000+01:00', $occurrence->getLastOccurredAt());
    }

    public function testFinishedWhenLastStatusIsTerminal(): void
    {
        $occurrence = $this->occurrence();
        $occurrence->appendStatus($this->makeStatus(Stage::Handled, '2026-11-17T20:41:09.000+01:00', 'consumer-app'));

        self::assertSame(Stage::Handled, $occurrence->getCurrentStage());
        self::assertSame('2026-11-17T20:41:09.000+01:00', $occurrence->getFinishedAt());
        self::assertFalse($occurrence->getHasPendingRetry());
    }

    public function testFillMetadataFirstHitWins(): void
    {
        $occurrence = $this->occurrence();
        $occurrence->fillMetadata('INV-1', 'ORD-99', 'Wystawienie faktury', ['billing']);

        $occurrence->fillMetadata('OTHER', null, null, ['surprise']);

        self::assertSame('INV-1', $occurrence->getMonitoringId());
        self::assertSame('ORD-99', $occurrence->getProcessId());
        self::assertSame('Wystawienie faktury', $occurrence->getName());
        self::assertSame(['billing'], $occurrence->getTags());
    }

    private function occurrence(): MessageOccurrence
    {
        return new MessageOccurrence(self::OCCURRENCE_ID, 'App\Message\SendInvoice');
    }

    private function makeStatus(Stage $stage, string $occurredAt, string $application): MessageOccurrenceStatus
    {
        $ts = (int) (new \DateTimeImmutable($occurredAt))->format('Uu');

        return new MessageOccurrenceStatus($stage, $occurredAt, $ts, $application);
    }
}
