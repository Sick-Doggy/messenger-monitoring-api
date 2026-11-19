<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Document\Monitor;
use App\Dto\MonitorInput;
use App\Dto\MonitorOutput;
use App\Dto\MonitorPatchInput;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Zapis: pełna aktualizacja (PUT) i częściowa (PATCH) wystąpienia.
 * occurrenceId jest kluczem korelacji/upsertu — nie może być zmieniany.
 *
 * @implements ProcessorInterface<MonitorInput|MonitorPatchInput, MonitorOutput>
 */
final class UpdateMonitorProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly DocumentManager $documentManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MonitorOutput
    {
        $monitor = $this->search($uriVariables['occurrenceId'] ?? null);

        if (null === $monitor) {
            throw new NotFoundHttpException('Monitor not found.');
        }

        if ($data instanceof MonitorPatchInput) {
            $this->applyPatch($monitor, $data);
        } else {
            if ($data->occurrenceId !== $monitor->getOccurrenceId()) {
                throw new UnprocessableEntityHttpException('occurrenceId in body does not match the URI.');
            }
            $monitor->updateDefinition(
                $data->className,
                $data->monitoringId,
                $data->processId,
                $data->name,
                $data->tags,
            );
        }

        $monitor->touch();
        $this->documentManager->flush();

        return MonitorOutput::fromMonitor($monitor);
    }

    private function search(?string $occurrenceId): ?Monitor
    {
        if (null === $occurrenceId) {
            return null;
        }

        $repository = $this->documentManager->getRepository(Monitor::class);

        return $repository->find($occurrenceId);
    }

    private function applyPatch(Monitor $monitor, MonitorPatchInput $input): void
    {
        if (null !== $input->className) {
            $monitor->setClassName($input->className);
        }
        if (null !== $input->monitoringId) {
            $monitor->setMonitoringId($input->monitoringId);
        }
        if (null !== $input->processId) {
            $monitor->setProcessId($input->processId);
        }
        if (null !== $input->name) {
            $monitor->setName($input->name);
        }
        if (null !== $input->tags) {
            $monitor->setTags($input->tags);
        }
    }
}
