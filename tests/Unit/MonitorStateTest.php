<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\ApiResource\MonitorState\Document\MonitorState;
use App\ApiResource\MonitorState\Document\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

#[CoversClass(MonitorState::class)]
final class MonitorStateTest extends TestCase
{
    private const string OCCURRENCE_ID = '6b2f4a1e-9c3d-4f0e-8f7a-1b2c3d4e5f60';

    public function testGetsServerGeneratedUuidV4AndKeepsOccurrenceId(): void
    {
        $state = new MonitorState(self::OCCURRENCE_ID, Stage::Sent, '2026-11-20T10:00:00.123456+00:00', 'shop');
        $other = new MonitorState(self::OCCURRENCE_ID, Stage::Sent, '2026-11-20T10:00:00.123456+00:00', 'shop');

        self::assertInstanceOf(UuidV4::class, Uuid::fromString($state->getId()));
        self::assertNotSame($state->getId(), $other->getId());
        self::assertSame(self::OCCURRENCE_ID, $state->getOccurrenceId());
    }

    public function testKeepsOccurredAtAndComputesMicrosecondTimestamp(): void
    {
        $state = new MonitorState(self::OCCURRENCE_ID, Stage::Sent, '2026-11-20T10:00:00.123456+00:00', 'shop');

        self::assertSame('2026-11-20T10:00:00.123456+00:00', $state->getOccurredAt());
        self::assertSame(1_795_168_800_123_456, $state->getOccurredAtTs());
    }

    public function testTimestampDistinguishesStatesWithinSameMillisecond(): void
    {
        $first = new MonitorState(self::OCCURRENCE_ID, Stage::Received, '2026-11-20T10:00:00.123001+00:00', 'billing');
        $second = new MonitorState(self::OCCURRENCE_ID, Stage::Handled, '2026-11-20T10:00:00.123002+00:00', 'billing');

        self::assertLessThan($second->getOccurredAtTs(), $first->getOccurredAtTs());
    }

    public function testTimestampIsTimezoneIndependent(): void
    {
        $utc = new MonitorState(self::OCCURRENCE_ID, Stage::Sent, '2026-11-20T10:00:00.000000+00:00', 'shop');
        $warsaw = new MonitorState(self::OCCURRENCE_ID, Stage::Sent, '2026-11-20T11:00:00.000000+01:00', 'shop');

        self::assertSame($utc->getOccurredAtTs(), $warsaw->getOccurredAtTs());
    }

    public function testMapsOptionalFields(): void
    {
        $state = new MonitorState(
            self::OCCURRENCE_ID,
            Stage::FailedRetry,
            '2026-11-20T10:00:00.000000+00:00',
            'billing',
            environment: 'prod',
            busName: 'messenger.bus.default',
            transportName: 'async',
            handlerName: 'App\MessageHandler\SendInvoiceHandler',
            errorClass: 'RuntimeException',
            errorMessage: 'Timeout',
        );

        self::assertSame(Stage::FailedRetry, $state->getStage());
        self::assertSame('billing', $state->getApplication());
        self::assertSame('prod', $state->getEnvironment());
        self::assertSame('messenger.bus.default', $state->getBusName());
        self::assertSame('async', $state->getTransportName());
        self::assertSame('App\MessageHandler\SendInvoiceHandler', $state->getHandlerName());
        self::assertSame('RuntimeException', $state->getErrorClass());
        self::assertSame('Timeout', $state->getErrorMessage());
    }

    #[DataProvider('invalidOccurredAt')]
    public function testRejectsInvalidOccurredAt(string $occurredAt): void
    {
        self::assertNull(MonitorState::parseOccurredAt($occurredAt));

        $this->expectException(\InvalidArgumentException::class);

        new MonitorState(self::OCCURRENCE_ID, Stage::Sent, $occurredAt, 'shop');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidOccurredAt(): iterable
    {
        yield 'nie data' => ['not-a-date'];
        yield 'miesiąc 13' => ['2026-13-45T10:00:00+00:00'];
        yield 'godzina 25' => ['2026-11-20T25:61:00+00:00'];
        yield '30 lutego (PHP przesunąłby na 2 marca)' => ['2026-02-30T10:00:00+00:00'];
        yield 'bez strefy' => ['2026-11-20T10:00:00'];
        yield 'strefa bez dwukropka' => ['2026-11-20T10:00:00+0100'];
        yield 'spacja zamiast T' => ['2026-11-20 10:00:00+00:00'];
        yield 'ułamek ponad 6 cyfr' => ['2026-11-20T10:00:00.1234567+00:00'];
    }

    #[DataProvider('validOccurredAt')]
    public function testAcceptsRfc3339Variants(string $occurredAt, int $expectedTs): void
    {
        self::assertSame($expectedTs, (new MonitorState(self::OCCURRENCE_ID, Stage::Sent, $occurredAt, 'shop'))->getOccurredAtTs());
    }

    /** @return iterable<string, array{string, int}> */
    public static function validOccurredAt(): iterable
    {
        yield 'mikrosekundy' => ['2026-11-20T10:00:00.123456+00:00', 1_795_168_800_123_456];
        yield 'bez ułamka' => ['2026-11-20T10:00:00+00:00', 1_795_168_800_000_000];
        yield 'strefa Z' => ['2026-11-20T10:00:00Z', 1_795_168_800_000_000];
        yield 'krótki ułamek' => ['2026-11-20T10:00:00.5Z', 1_795_168_800_500_000];
        yield 'przesunięcie strefy' => ['2026-11-20T11:00:00+01:00', 1_795_168_800_000_000];
        yield '29 lutego w roku przestępnym' => ['2028-02-29T00:00:00Z', 1_835_395_200_000_000];
    }
}
