<?php

declare(strict_types=1);

namespace App\Monitoring;

final class MessageOccurrence
{
    private ?string $monitoringId = null;
    private ?string $processId = null;
    private ?string $name = null;
    /** @var list<string> */
    private array $tags = [];
    /** @var list<OccurrenceStatus> posortowane po occurredAt ASC (sort stabilny) */
    private array $statuses = [];

    public function __construct(
        public readonly string $occurrenceId,
        public readonly string $className,
    ) {
    }

    /**
     * Pierwsze trafienie ustala metadane; kolejne uzupełniają tylko puste pola.
     *
     * @param list<string> $tags
     */
    public function fillMetadata(?string $monitoringId, ?string $processId, ?string $name, array $tags): void
    {
        $this->monitoringId ??= $monitoringId;
        $this->processId ??= $processId;
        $this->name ??= $name;

        if ([] === $this->tags && [] !== $tags) {
            $this->tags = array_values(array_unique($tags));
        }
    }

    public function monitoringId(): ?string
    {
        return $this->monitoringId;
    }

    public function processId(): ?string
    {
        return $this->processId;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    /** @return list<string> */
    public function tags(): array
    {
        return $this->tags;
    }

    /** @return list<OccurrenceStatus> */
    public function statuses(): array
    {
        return $this->statuses;
    }

    /** Idempotencja: zwraca false, gdy stan był już zarejestrowany (duplikat). */
    public function append(OccurrenceStatus $status): bool
    {
        if ($this->hasStatus($status)) {
            return false;
        }

        $this->statuses[] = $status;
        usort($this->statuses, self::byOccurredAt(...));

        return true;
    }

    public function hasStatus(OccurrenceStatus $status): bool
    {
        $key = $status->identityKey();

        foreach ($this->statuses as $existing) {
            if ($existing->identityKey() === $key) {
                return true;
            }
        }

        return false;
    }

    public function currentStage(): ?Stage
    {
        return $this->lastStatus()?->stage;
    }

    /** Liczba przebiegów: failed-retry wystąpiła raz na wypadek + pierwsza próba. */
    public function attempts(): int
    {
        $retries = array_filter($this->statuses, static fn (OccurrenceStatus $s): bool => $s->stage->countsAsRetry());

        return [] === $this->statuses ? 0 : count($retries) + 1;
    }

    /** Zakończenie tylko wtedy, gdy OSTATNI stan jest terminalny (handled | failed-final). */
    public function finishedAt(): ?\DateTimeImmutable
    {
        $last = $this->lastStatus();

        return null !== $last && $last->stage->isTerminal() ? $last->occurredAt : null;
    }

    public function hasPendingRetry(): bool
    {
        return Stage::FailedRetry === $this->currentStage();
    }

    public function firstOccurredAt(): ?\DateTimeImmutable
    {
        return [] === $this->statuses ? null : $this->statuses[0]->occurredAt;
    }

    public function lastOccurredAt(): ?\DateTimeImmutable
    {
        return $this->lastStatus()?->occurredAt;
    }

    private function lastStatus(): ?OccurrenceStatus
    {
        return $this->statuses === [] ? null : $this->statuses[count($this->statuses) - 1];
    }

    private static function byOccurredAt(OccurrenceStatus $a, OccurrenceStatus $b): int
    {
        return $a->occurredAt <=> $b->occurredAt;
    }
}
