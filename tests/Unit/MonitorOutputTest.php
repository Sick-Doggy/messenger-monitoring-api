<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\ApiResource\Monitor\Document\Monitor;
use App\ApiResource\Monitor\Dto\MonitorOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MonitorOutput::class)]
final class MonitorOutputTest extends TestCase
{
    public function testFromMonitorMapsAllFields(): void
    {
        $createdAt = new \DateTimeImmutable('2026-11-19T10:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2026-11-19T11:00:00+00:00');

        $monitor = new Monitor(
            occurrenceId: '6b2f4a1e-9c3d-4f0e-8f7a-1b2c3d4e5f60',
            className: 'App\Message\SendInvoice',
            monitoringId: 'invoiceId-42',
            processId: 'spring-cleaning-2026',
            name: 'Faktury listopad',
            tags: ['billing', 'urgent'],
        );
        $this->setTimestamps($monitor, $createdAt, $updatedAt);

        $output = MonitorOutput::fromMonitor($monitor);

        self::assertSame('6b2f4a1e-9c3d-4f0e-8f7a-1b2c3d4e5f60', $output->occurrenceId);
        self::assertSame('App\Message\SendInvoice', $output->className);
        self::assertSame('invoiceId-42', $output->monitoringId);
        self::assertSame('spring-cleaning-2026', $output->processId);
        self::assertSame('Faktury listopad', $output->name);
        self::assertSame(['billing', 'urgent'], $output->tags);
        self::assertSame($createdAt, $output->createdAt);
        self::assertSame($updatedAt, $output->updatedAt);
    }

    public function testDefaultsForOptionalFields(): void
    {
        $monitor = new Monitor(
            occurrenceId: '6b2f4a1e-9c3d-4f0e-8f7a-1b2c3d4e5f60',
            className: 'App\Message\SendInvoice',
        );

        $output = MonitorOutput::fromMonitor($monitor);

        self::assertNull($output->monitoringId);
        self::assertNull($output->processId);
        self::assertNull($output->name);
        self::assertSame([], $output->tags);
    }

    private function setTimestamps(Monitor $monitor, \DateTimeImmutable $createdAt, \DateTimeImmutable $updatedAt): void
    {
        $reflection = new \ReflectionClass($monitor);
        $reflection->getProperty('createdAt')->setValue($monitor, $createdAt);
        $reflection->getProperty('updatedAt')->setValue($monitor, $updatedAt);
    }
}
