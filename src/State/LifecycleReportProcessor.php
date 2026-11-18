<?php

declare(strict_types=1);

namespace App\State;

use App\ApiResource\LifecycleReport;
use App\ApiResource\LifecycleReportReceipt;
use App\ApiResource\ReportedStatus;
use App\Monitoring\MessageOccurrence;
use App\Monitoring\OccurrenceStatus;
use App\Monitoring\Stage;
use App\Persistence\MessageOccurrenceRepositoryInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Ingest: upsert nagłówka + append stanów (idempotentnie).
 * Walidacja DTO następuje automatycznie przed tym procesorem (mostek symfony/validator).
 */
/** @implements ProcessorInterface<\App\ApiResource\LifecycleReport, \App\ApiResource\LifecycleReportReceipt> */
final class LifecycleReportProcessor implements ProcessorInterface
{
    public const INGEST_RECEIPT_ATTRIBUTE = 'lifecycle_report.receipt';

    public function __construct(
        private readonly MessageOccurrenceRepositoryInterface $repository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LifecycleReportReceipt
    {
        $existing = $this->repository->get((string) $data->occurrenceId);
        $created = null === $existing;

        $occurrence = $existing ?? new MessageOccurrence((string) $data->occurrenceId, (string) $data->className);
        $occurrence->fillMetadata($data->monitoringId, $data->processId, $data->name, $data->tags);

        $accepted = 0;
        $skipped = 0;

        foreach ($data->statuses as $reported) {
            if ($occurrence->append($this->toDomainStatus($reported))) {
                ++$accepted;
            } else {
                ++$skipped;
            }
        }

        $this->repository->save($occurrence);

        return $this->receipt($occurrence, $created, $accepted, $skipped);
    }

    private function toDomainStatus(ReportedStatus $reported): OccurrenceStatus
    {
        return new OccurrenceStatus(
            Stage::from((string) $reported->stage),
            new \DateTimeImmutable((string) $reported->occurredAt),
            (string) $reported->application,
            environment: $reported->environment,
            busName: $reported->busName,
            transportName: $reported->transportName,
            handlerName: $reported->handlerName,
            errorClass: $reported->errorClass,
            errorMessage: $reported->errorMessage,
            payload: $reported->payload,
        );
    }

    private function receipt(MessageOccurrence $occurrence, bool $created, int $accepted, int $skipped): LifecycleReportReceipt
    {
        $receipt = new LifecycleReportReceipt($occurrence->occurrenceId, $created, $accepted, $skipped);
        $this->requestStack->getMainRequest()?->attributes->set(self::INGEST_RECEIPT_ATTRIBUTE, $receipt);

        return $receipt;
    }
}
