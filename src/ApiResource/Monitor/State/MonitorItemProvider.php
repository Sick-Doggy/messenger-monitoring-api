<?php

declare(strict_types=1);

namespace App\ApiResource\Monitor\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Monitor\Document\Monitor;
use App\ApiResource\Monitor\Dto\MonitorOutput;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Odczyt pojedynczego wystąpienia → MonitorOutput.
 *
 * @implements ProviderInterface<MonitorOutput>
 */
final class MonitorItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly DocumentManager $documentManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MonitorOutput
    {
        $monitor = $this->search(...$uriVariables);

        if (null === $monitor) {
            throw new NotFoundHttpException('Monitor not found.');
        }

        return MonitorOutput::fromMonitor($monitor);
    }

    private function search(string $occurrenceId): ?Monitor
    {
        $repository = $this->documentManager->getRepository(Monitor::class);

        return $repository->find($occurrenceId);
    }
}
