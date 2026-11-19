<?php

declare(strict_types=1);

namespace App\ApiResource\Monitor\Document;

use App\ApiResource\Monitor\Repository\MonitorRepository;
use Doctrine\ODM\MongoDB\Event\PreUpdateEventArgs;
use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;
use Doctrine\ODM\MongoDB\Mapping\Attribute\HasLifecycleCallbacks;
use Doctrine\ODM\MongoDB\Mapping\Attribute\PrePersist;
use Doctrine\ODM\MongoDB\Mapping\Attribute\PreUpdate;

/**
 * Wystąpienie monitorowanej wiadomości.
 *
 * - occurrenceId jest _id dokumentu (uuid v4 nadawany przez nadawcę, klucz korelacji i upsertu),
 * - monitoringId i processId są indeksowane (główne filtry UX),
 * - pusta lista tags = brak etykiet.
 */
#[ODM\Document(collection: 'monitors', repositoryClass: MonitorRepository::class)]
#[ODM\Index(keys: ['monitoringId' => 'asc'])]
#[ODM\Index(keys: ['processId' => 'asc'])]
#[HasLifecycleCallbacks]
final class Monitor
{
    #[ODM\Id(strategy: 'none', type: 'string')]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    private string $className;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $monitoringId;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $processId;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $name;

    /** @var list<string> */
    #[ODM\Field(type: 'collection')]
    private array $tags = [];

    #[ODM\Field(type: 'date_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ODM\Field(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @param list<string> $tags */
    public function __construct(
        string $occurrenceId,
        string $className,
        ?string $monitoringId = null,
        ?string $processId = null,
        ?string $name = null,
        array $tags = [],
    ) {
        $this->id = $occurrenceId;
        $this->className = $className;
        $this->monitoringId = $monitoringId;
        $this->processId = $processId;
        $this->name = $name;
        $this->tags = $tags;
        $this->createdAt = new \DateTimeImmutable();
    }

    #[PrePersist]
    public function prePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    #[PreUpdate]
    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $this->touch();

        $documentManager = $event->getDocumentManager();
        $documentManager->getUnitOfWork()->recomputeSingleDocumentChangeSet(
            $documentManager->getClassMetadata(self::class),
            $this,
        );
    }

    /** occurrenceId jest identyfikatorem technicznym (_id dokumentu). */
    public function getOccurrenceId(): ?string
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

    /**
     * Pełne nadpisanie definicji (upsert/PUT).
     */
    /** @param list<string> $tags */
    public function updateDefinition(
        string $className,
        ?string $monitoringId,
        ?string $processId,
        ?string $name,
        array $tags,
    ): void {
        $this->className = $className;
        $this->monitoringId = $monitoringId;
        $this->processId = $processId;
        $this->name = $name;
        $this->tags = $tags;
    }

    public function setClassName(string $className): void
    {
        $this->className = $className;
    }

    public function setMonitoringId(?string $monitoringId): void
    {
        $this->monitoringId = $monitoringId;
    }

    public function setProcessId(?string $processId): void
    {
        $this->processId = $processId;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /** @param list<string> $tags */
    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
