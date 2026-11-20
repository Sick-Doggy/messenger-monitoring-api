<?php

declare(strict_types=1);

namespace App\ApiResource\Monitor\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Monitor\Document\Monitor;
use App\ApiResource\MonitorState\Repository\MonitorStateRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Zapis: usunięcie wystąpienia (DELETE) razem z jego historią stanów.
 *
 * @implements ProcessorInterface<null, null>
 */
final class DeleteMonitorProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly MonitorStateRepository $states,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $occurrenceId = $uriVariables['occurrenceId'] ?? null;
        $monitor = null === $occurrenceId
            ? null
            : $this->documentManager->getRepository(Monitor::class)->find($occurrenceId);

        if (null === $monitor) {
            throw new NotFoundHttpException('Monitor not found.');
        }

        $this->documentManager->remove($monitor);
        $this->documentManager->flush();

        // stany są dostępne tylko pod /api/monitors/{occurrenceId}/states — bez Monitora byłyby osierocone
        $this->states->deleteOfMonitor((string) $occurrenceId);

        return null;
    }
}
