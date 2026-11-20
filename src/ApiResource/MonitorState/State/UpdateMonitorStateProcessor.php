<?php

declare(strict_types=1);

namespace App\ApiResource\MonitorState\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MonitorState\Document\MonitorState;
use App\ApiResource\MonitorState\Document\Stage;
use App\ApiResource\MonitorState\Dto\MonitorStateInput;
use App\ApiResource\MonitorState\Dto\MonitorStateOutput;
use App\ApiResource\MonitorState\Dto\MonitorStatePatchInput;
use App\ApiResource\MonitorState\Repository\MonitorStateRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Zapis: pełna (PUT) i częściowa (PATCH) aktualizacja stanu.
 * Zmiana, po której wpis byłby duplikatem innego stanu tego wystąpienia = 409.
 *
 * @implements ProcessorInterface<MonitorStateInput|MonitorStatePatchInput, MonitorStateOutput>
 */
final class UpdateMonitorStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MonitorStateRepository $states,
        private readonly DocumentManager $documentManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MonitorStateOutput
    {
        $state = $this->states->findInMonitor(
            (string) ($uriVariables['occurrenceId'] ?? ''),
            (string) ($uriVariables['id'] ?? ''),
        );

        if (null === $state) {
            throw new NotFoundHttpException('Monitor state not found.');
        }

        if ($data instanceof MonitorStatePatchInput) {
            $this->applyPatch($state, $data);
        } else {
            $state->replace(
                Stage::from($data->stage),
                $data->occurredAt,
                $data->application,
                $data->environment,
                $data->busName,
                $data->transportName,
                $data->handlerName,
                $data->errorClass,
                $data->errorMessage,
            );
        }

        if ($this->states->hasOtherWithSameKey($state)) {
            throw new ConflictHttpException('Taki stan (stage + occurredAt + application) już istnieje dla tego wystąpienia.');
        }

        $this->documentManager->flush();

        return MonitorStateOutput::fromState($state);
    }

    /** PATCH: null = bez zmiany (CONVENTIONS.md, Modele i operacje). */
    private function applyPatch(MonitorState $state, MonitorStatePatchInput $input): void
    {
        $stage = $state->getStage();
        $occurredAt = $state->getOccurredAt();
        $application = $state->getApplication();
        $environment = $state->getEnvironment();
        $busName = $state->getBusName();
        $transportName = $state->getTransportName();
        $handlerName = $state->getHandlerName();
        $errorClass = $state->getErrorClass();
        $errorMessage = $state->getErrorMessage();

        if (null !== $input->stage) {
            $stage = Stage::from($input->stage);
        }
        if (null !== $input->occurredAt) {
            $occurredAt = $input->occurredAt;
        }
        if (null !== $input->application) {
            $application = $input->application;
        }
        if (null !== $input->environment) {
            $environment = $input->environment;
        }
        if (null !== $input->busName) {
            $busName = $input->busName;
        }
        if (null !== $input->transportName) {
            $transportName = $input->transportName;
        }
        if (null !== $input->handlerName) {
            $handlerName = $input->handlerName;
        }
        if (null !== $input->errorClass) {
            $errorClass = $input->errorClass;
        }
        if (null !== $input->errorMessage) {
            $errorMessage = $input->errorMessage;
        }

        $state->replace($stage, $occurredAt, $application, $environment, $busName, $transportName, $handlerName, $errorClass, $errorMessage);
    }
}
