<?php

declare(strict_types=1);

namespace App\ApiResource\Monitor\Dto;

use ApiPlatform\Metadata\ApiProperty;
use App\ApiResource\Monitor\Document\Monitor;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Output dla wszystkich operacji odczytu i zapisu zasobu Monitor.
 */
final class MonitorOutput
{
    #[Groups(['monitor:read'])]
    #[ApiProperty(identifier: true)]
    public string $occurrenceId = '';

    #[Groups(['monitor:read'])]
    public string $className = '';

    #[Groups(['monitor:read'])]
    public ?string $monitoringId = null;

    #[Groups(['monitor:read'])]
    public ?string $processId = null;

    #[Groups(['monitor:read'])]
    public ?string $name = null;

    /** @var list<string> */
    #[Groups(['monitor:read'])]
    public array $tags = [];

    #[Groups(['monitor:read'])]
    public ?\DateTimeImmutable $createdAt = null;

    #[Groups(['monitor:read'])]
    public ?\DateTimeImmutable $updatedAt = null;

    public static function fromMonitor(Monitor $monitor): self
    {
        $output = new self();
        $output->occurrenceId = $monitor->getOccurrenceId() ?? '';
        $output->className = $monitor->getClassName();
        $output->monitoringId = $monitor->getMonitoringId();
        $output->processId = $monitor->getProcessId();
        $output->name = $monitor->getName();
        $output->tags = $monitor->getTags();
        $output->createdAt = $monitor->getCreatedAt();
        $output->updatedAt = $monitor->getUpdatedAt();

        return $output;
    }
}
