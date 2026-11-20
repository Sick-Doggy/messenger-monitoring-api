<?php

declare(strict_types=1);

namespace App\ApiResource\MonitorState\Document;

use App\ApiResource\MonitorState\Repository\MonitorStateRepository;
use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;
use Symfony\Component\Uid\Uuid;

/**
 * Jeden wpis w historii stanów wystąpienia wiadomości (Monitor wskazany przez occurrenceId).
 *
 * - id to uuid v4 nadawany przez serwer,
 * - occurredAt trzymamy jako string (kontrakt: RFC3339 z mikrosekundami),
 *   a occurredAtTs (mikrosekundy od epoki) służy do sortowania — BSON Date ma tylko milisekundy,
 * - ten sam occurrenceId + stage + occurredAtTs + application = ten sam wpis (duplikat raportu);
 *   pilnuje tego unikalny indeks i upsert w MonitorStateRepository::createOrGet(),
 * - errorClass/errorMessage wypełniane tylko dla etapów błędu.
 */
#[ODM\Document(collection: 'monitor_states', repositoryClass: MonitorStateRepository::class)]
// klucz naturalny; prefiks occurrenceId + stage + occurredAtTs obsługuje też filtr ?stage= z sortowaniem
#[ODM\UniqueIndex(keys: ['occurrenceId' => 'asc', 'stage' => 'asc', 'occurredAtTs' => 'asc', 'application' => 'asc'])]
// lista bez filtrów (sort chronologiczny)
#[ODM\Index(keys: ['occurrenceId' => 'asc', 'occurredAtTs' => 'asc'])]
// filtr ?application=
#[ODM\Index(keys: ['occurrenceId' => 'asc', 'application' => 'asc', 'occurredAtTs' => 'asc'])]
final class MonitorState
{
    public const OCCURRED_AT_MESSAGE = 'occurredAt musi być istniejącą datą RFC3339, np. 2026-11-20T10:00:00.123456+00:00.';

    #[ODM\Id(strategy: 'none', type: 'string')]
    private string $id;

    #[ODM\Field(type: 'string')]
    private string $occurrenceId;

    #[ODM\Field(type: 'string', enumType: Stage::class)]
    private Stage $stage;

    #[ODM\Field(type: 'string')]
    private string $occurredAt;

    #[ODM\Field(type: 'int')]
    private int $occurredAtTs;

    #[ODM\Field(type: 'string')]
    private string $application;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $environment;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $busName;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $transportName;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $handlerName;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $errorClass;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $errorMessage;

    /**
     * @param string $occurredAt RFC3339 z mikrosekundami, np. 2026-11-20T10:00:00.123456+00:00
     *
     * @throws \InvalidArgumentException gdy occurredAt nie jest poprawną datą
     */
    public function __construct(
        string $occurrenceId,
        Stage $stage,
        string $occurredAt,
        string $application,
        ?string $environment = null,
        ?string $busName = null,
        ?string $transportName = null,
        ?string $handlerName = null,
        ?string $errorClass = null,
        ?string $errorMessage = null,
    ) {
        $this->id = Uuid::v4()->toRfc4122();
        $this->occurrenceId = $occurrenceId;
        $this->replace($stage, $occurredAt, $application, $environment, $busName, $transportName, $handlerName, $errorClass, $errorMessage);
    }

    /**
     * Pełne nadpisanie danych wpisu (PUT/PATCH). id i occurrenceId się nie zmieniają.
     *
     * @throws \InvalidArgumentException gdy occurredAt nie jest poprawną datą
     */
    public function replace(
        Stage $stage,
        string $occurredAt,
        string $application,
        ?string $environment,
        ?string $busName,
        ?string $transportName,
        ?string $handlerName,
        ?string $errorClass,
        ?string $errorMessage,
    ): void {
        $this->stage = $stage;
        $this->occurredAt = $occurredAt;
        $this->occurredAtTs = self::toMicroseconds($occurredAt);
        $this->application = $application;
        $this->environment = $environment;
        $this->busName = $busName;
        $this->transportName = $transportName;
        $this->handlerName = $handlerName;
        $this->errorClass = $errorClass;
        $this->errorMessage = $errorMessage;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOccurrenceId(): string
    {
        return $this->occurrenceId;
    }

    public function getStage(): Stage
    {
        return $this->stage;
    }

    public function getOccurredAt(): string
    {
        return $this->occurredAt;
    }

    public function getOccurredAtTs(): int
    {
        return $this->occurredAtTs;
    }

    public function getApplication(): string
    {
        return $this->application;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function getBusName(): ?string
    {
        return $this->busName;
    }

    public function getTransportName(): ?string
    {
        return $this->transportName;
    }

    public function getHandlerName(): ?string
    {
        return $this->handlerName;
    }

    public function getErrorClass(): ?string
    {
        return $this->errorClass;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Ścisłe parsowanie occurredAt (RFC3339): YYYY-MM-DDTHH:MM:SS, opcjonalny ułamek do 6 cyfr,
     * strefa Z albo ±HH:MM. Data nieistniejąca (np. 2026-02-30, miesiąc 13, godzina 25) = null.
     * Jedyne miejsce tej reguły — używa go walidacja DTO (422) i liczenie occurredAtTs.
     */
    public static function parseOccurredAt(string $occurredAt): ?\DateTimeImmutable
    {
        // createFromFormat('P') przyjąłby też +0100 — RFC3339 wymaga dwukropka
        if (1 !== preg_match('/(Z|[+-]\d{2}:\d{2})$/', $occurredAt)) {
            return null;
        }

        foreach (['!Y-m-d\TH:i:sP', '!Y-m-d\TH:i:s.uP'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $occurredAt);

            // getLastErrors() !== false = ostrzeżenia, np. "The parsed date was invalid" dla 30 lutego
            if (false !== $date && false === \DateTimeImmutable::getLastErrors()) {
                return $date;
            }
        }

        return null;
    }

    private static function toMicroseconds(string $occurredAt): int
    {
        $date = self::parseOccurredAt($occurredAt);

        if (null === $date) {
            throw new \InvalidArgumentException(sprintf('occurredAt "%s" nie jest poprawną datą RFC3339.', $occurredAt));
        }

        return (int) $date->format('Uu'); // sekundy + 6 cyfr mikrosekund
    }
}
