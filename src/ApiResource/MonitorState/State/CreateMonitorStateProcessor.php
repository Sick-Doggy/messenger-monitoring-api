<?php

declare(strict_types=1);

namespace App\ApiResource\MonitorState\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Monitor\Repository\MonitorRepository;
use App\ApiResource\MonitorState\Document\MonitorState;
use App\ApiResource\MonitorState\Document\Stage;
use App\ApiResource\MonitorState\Dto\MonitorStateInput;
use App\ApiResource\MonitorState\Dto\MonitorStateOutput;
use App\ApiResource\MonitorState\Repository\MonitorStateRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Zapis: nowy stan wystąpienia (POST /api/monitors/{occurrenceId}/states).
 * Idempotentny — ponowne zgłoszenie tego samego stanu zwraca istniejący wpis (to samo id).
 *
 * @implements ProcessorInterface<MonitorStateInput, MonitorStateOutput>
 */
final class CreateMonitorStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MonitorRepository $monitors,
        private readonly MonitorStateRepository $states,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MonitorStateOutput
    {
        $occurrenceId = (string) ($uriVariables['occurrenceId'] ?? '');

        if (null === $this->monitors->find($occurrenceId)) {
            throw new NotFoundHttpException('Monitor not found.');
        }

        $stored = $this->states->createOrGet(new MonitorState(
            $occurrenceId,
            Stage::from($data->stage),
            $data->occurredAt,
            $data->application,
            environment: $data->environment,
            busName: $data->busName,
            transportName: $data->transportName,
            handlerName: $data->handlerName,
            errorClass: $data->errorClass,
            errorMessage: $data->errorMessage,
        ));

        return MonitorStateOutput::fromState($stored);
    }
}
