<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/** Mongo — głłówka wystąpienia. Kontrakt odczytu żyje w \App\ApiResource\MessageOccurrence. */
#[ODM\Document(collection: 'message_occurrences')]
class MessageOccurrence
{
    /**
     * @param list<string> $tags
     * @param list<MessageOccurrenceStatus> $statuses
     */
    public function __construct(
        #[ODM\Id(type: 'string', strategy: 'NONE')]
        private string $id,
        #[ODM\Field(type: 'string')]
        private string $className,
        #[ODM\Field(type: 'string', nullable: true)]
        private ?string $monitoringId = null,
        #[ODM\Field(type: 'string', nullable: true)]
        private ?string $processId = null,
        #[ODM\Field(type: 'string', nullable: true)]
        private ?string $name = null,
        #[ODM\Field(type: 'collection')]
        private array $tags = [],
        #[ODM\EmbedMany(targetDocument: MessageOccurrenceStatus::class)]
        private array $statuses = [],
        #[ODM\Field(type: 'int', nullable: false)]
        private int $lastOccurredAtTs = 0,
    ) {
    }

    /** Pierwsze trafienie ustala metadane; kolejne uzupełniają tylko puste pola.
     * @param list<string> $tags */
    public function fillMetadata(?string $monitoringId, ?string $processId, ?string $name, array $tags): void
    {
        $this->monitoringId ??= $monitoringId;
        $this->processId ??= $processId;
        $this->name ??= $name;

        if ([] === $this->tags && [] !== $tags) {
            $this->tags = array_values(array_unique($tags));
        }
    }

    /** Idempotencja: zwraca false, gdy stan był już zarejestrowany (duplikat). */
    public function appendStatus(MessageOccurrenceStatus $status): bool
    {
        if ($this->hasStatus($status)) {
            return false;
        }

        $this->statuses[] = $status;
        usort($this->statuses, static fn (MessageOccurrenceStatus $a, MessageOccurrenceStatus $b): int => $a->getOccurredAtTs() <=> $b->getOccurredAtTs());
        $this->lastOccurredAtTs = $this->lastStatus()?->getOccurredAtTs() ?? 0;

        return true;
    }

    private function hasStatus(MessageOccurrenceStatus $status): bool
    {
        $key = $status->getStage()->value.'|'.$status->getOccurredAtTs().'|'.$status->getApplication();

        foreach ($this->statuses as $existing) {
            $existingKey = $existing->getStage()->value.'|'.$existing->getOccurredAtTs().'|'.$existing->getApplication();

            if ($existingKey === $key) {
                return true;
            }
        }

        return false;
    }

    public function currentStage(): ?Stage
    {
        return $this->lastStatus()?->getStage();
    }

    public function attempts(): int
    {
        if ([] === $this->statuses) {
            return 0;
        }

        $retries = array_filter($this->statuses, static fn (MessageOccurrenceStatus $s): bool => $s->getStage()->countsAsRetry());

        return count($retries) + 1;
    }

    public function finishedAt(): ?string
    {
        $last = $this->lastStatus();

        return null !== $last && $last->getStage()->isTerminal() ? $last->getOccurredAt() : null;
    }

    public function hasPendingRetry(): bool
    {
        return Stage::FailedRetry === $this->currentStage();
    }

    /** @return list<MessageOccurrenceStatus> */
    public function getStatuses(): array
    {
        $list = [];

        foreach ($this->statuses as $status) {
            $list[] = $status;
        }

        return $list;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getMonitoringId(): ?string
    {
        return $this->monitoringId;
    }

    public function getProcessId(): ?string
    {
        return $this->processId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /** @return list<string> */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getCurrentStage(): ?Stage
    {
        return $this->currentStage();
    }

    public function getAttempts(): int
    {
        return $this->attempts();
    }

    /** @return string|null Format kontraktowy (RFC3339 z mikrosekundami). */
    public function getFinishedAt(): ?string
    {
        return $this->finishedAt();
    }

    public function getHasPendingRetry(): bool
    {
        return $this->hasPendingRetry();
    }

    public function getFirstOccurredAt(): ?string
    {
        return [] === $this->statuses ? null : $this->statuses[0]->getOccurredAt();
    }

    public function getLastOccurredAt(): ?string
    {
        return $this->lastStatus()?->getOccurredAt();
    }

    public function getLastOccurredAtTs(): int
    {
        return $this->lastOccurredAtTs;
    }

    private function lastStatus(): ?MessageOccurrenceStatus
    {
        return [] === $this->statuses ? null : $this->statuses[count($this->statuses) - 1];
    }
}
