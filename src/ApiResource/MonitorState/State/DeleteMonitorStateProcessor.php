<?php

declare(strict_types=1);

namespace App\ApiResource\MonitorState\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MonitorState\Repository\MonitorStateRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Zapis: usunięcie stanu (DELETE /api/monitors/{occurrenceId}/states/{id}).
 *
 * @implements ProcessorInterface<null, null>
 */
final class DeleteMonitorStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MonitorStateRepository $states,
        private readonly DocumentManager $documentManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $state = $this->states->findInMonitor(
            (string) ($uriVariables['occurrenceId'] ?? ''),
            (string) ($uriVariables['id'] ?? ''),
        );

        if (null === $state) {
            throw new NotFoundHttpException('Monitor state not found.');
        }

        $this->documentManager->remove($state);
        $this->documentManager->flush();

        return null;
    }
}
