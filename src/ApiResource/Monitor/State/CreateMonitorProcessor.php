<?php

declare(strict_types=1);

namespace App\ApiResource\Monitor\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Monitor\Document\Monitor;
use App\ApiResource\Monitor\Dto\MonitorInput;
use App\ApiResource\Monitor\Dto\MonitorOutput;
use Doctrine\ODM\MongoDB\DocumentManager;

/**
 * Zapis: upsert wystąpienia po occurrenceId (POST).
 * Brak dokumentu → insert, istniejący → pełne nadpisanie pól definicji.
 *
 * @implements ProcessorInterface<MonitorInput, MonitorOutput>
 */
final class CreateMonitorProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly DocumentManager $documentManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MonitorOutput
    {
        $repository = $this->documentManager->getRepository(Monitor::class);
        $monitor = $repository->find($data->occurrenceId);

        if (null === $monitor) {
            $monitor = new Monitor(
                occurrenceId: $data->occurrenceId,
                className: $data->className,
                monitoringId: $data->monitoringId,
                processId: $data->processId,
                name: $data->name,
                tags: $data->tags,
            );
            $this->documentManager->persist($monitor);
        } else {
            $monitor->updateDefinition(
                $data->className,
                $data->monitoringId,
                $data->processId,
                $data->name,
                $data->tags,
            );
        }

        $this->documentManager->flush();

        return MonitorOutput::fromMonitor($monitor);
    }
}
