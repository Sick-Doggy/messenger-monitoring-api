<?php

declare(strict_types=1);

namespace App\ApiResource\MonitorState\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MonitorState\Dto\MonitorStateOutput;
use App\ApiResource\MonitorState\Repository\MonitorStateRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Odczyt pojedynczego stanu (GET /api/monitors/{occurrenceId}/states/{id}).
 * Stan innego wystąpienia niż w URI = 404.
 *
 * @implements ProviderInterface<MonitorStateOutput>
 */
final class MonitorStateItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly MonitorStateRepository $states,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MonitorStateOutput
    {
        $state = $this->states->findInMonitor(
            (string) ($uriVariables['occurrenceId'] ?? ''),
            (string) ($uriVariables['id'] ?? ''),
        );

        if (null === $state) {
            throw new NotFoundHttpException('Monitor state not found.');
        }

        return MonitorStateOutput::fromState($state);
    }
}
