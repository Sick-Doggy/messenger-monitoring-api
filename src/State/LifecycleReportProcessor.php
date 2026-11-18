<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\LifecycleReport;
use App\ApiResource\LifecycleReportReceipt;
use App\ApiResource\ReportStatus;
use App\Document\MessageOccurrence;
use App\Document\MessageOccurrenceStatus;
use App\Document\Stage;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Ingest: upsert główki + append stanów (idempotentnie).
 * Uwaga: BSON Date nie ma mikrosekund — occurredAt przechowujemy jako string + ts w µs.
 */
/** @implements ProcessorInterface<\App\ApiResource\LifecycleReport, \App\ApiResource\LifecycleReportReceipt> */
final class LifecycleReportProcessor implements ProcessorInterface
{
    public const INGEST_RECEIPT_ATTRIBUTE = 'lifecycle_report.receipt';

    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LifecycleReportReceipt
    {
        $existing = $this->documentManager->find(MessageOccurrence::class, (string) $data->occurrenceId);
        $created = null === $existing;

        $occurrence = $existing ?? new MessageOccurrence((string) $data->occurrenceId, (string) $data->className);
        $occurrence->fillMetadata($data->monitoringId, $data->processId, $data->name, $data->tags);

        $accepted = 0;
        $skipped = 0;

        foreach ($data->statuses as $reported) {
            if ($occurrence->appendStatus($this->toDocumentStatus($reported))) {
                ++$accepted;
            } else {
                ++$skipped;
            }
        }

        $this->documentManager->persist($occurrence);
        $this->documentManager->flush();

        return $this->receipt($occurrence, $created, $accepted, $skipped);
    }

    private function toDocumentStatus(ReportStatus $reported): MessageOccurrenceStatus
    {
        $occurredAt = (string) $reported->occurredAt;
        $timestamp = $this->microsecondsTs($occurredAt);

        return new MessageOccurrenceStatus(
            Stage::from((string) $reported->stage),
            $occurredAt,
            $timestamp,
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

    private function microsecondsTs(string $occurredAt): int
    {
        $date = new \DateTimeImmutable($occurredAt);

        return (int) $date->format('Uu'); // sekundy i mikrosekundy: 18 cyfr
    }

    private function receipt(MessageOccurrence $occurrence, bool $created, int $accepted, int $skipped): LifecycleReportReceipt
    {
        $receipt = new LifecycleReportReceipt($occurrence->getId(), $created, $accepted, $skipped);
        $this->requestStack->getMainRequest()?->attributes->set(self::INGEST_RECEIPT_ATTRIBUTE, $receipt);

        return $receipt;
    }
}
