<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Document\Monitor;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Zapis: usunięcie wystąpienia (DELETE).
 *
 * @implements ProcessorInterface<null, null>
 */
final class DeleteMonitorProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly DocumentManager $documentManager,
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

        return null;
    }
}
