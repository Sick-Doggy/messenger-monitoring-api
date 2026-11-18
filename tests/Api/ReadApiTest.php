<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Monitoring\MessageOccurrence as OccurrenceAggregate;
use App\Monitoring\OccurrenceStatus;
use App\Monitoring\Stage;
use App\Persistence\InMemoryMessageOccurrenceRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReadApiTest extends WebTestCase
{
    public function testListWithDefaultSortingAndPaginationEnvelope(): void
    {
        $client = self::createClient();
        $repository = $this->repository();
        $repository->save(self::occurrence(
            '8b4e8ee1-2952-49aa-bddc-3a909b933185',
            'App\Message\SendInvoice',
            'INV-123',
            'ORD-99',
            ['billing', 'invoices'],
            new \DateTimeImmutable('2026-11-17T20:41:03.123+01:00'),
        ));
        $repository->save(self::occurrence(
            'c2a7f3d4-1111-2222-3333-444455556666',
            'App\Message\SendReport',
            'REP-1',
            'ORD-99',
            ['reports'],
            new \DateTimeImmutable('2026-11-17T20:35:00.000+01:00'),
        ));

        $client->request('GET', '/api/message-occurrences');

        self::assertResponseStatusCodeSame(200);
        $content = self::content($client);

        self::assertSame(2, $content['totalItems']);
        self::assertSame(1, $content['page']);
        self::assertSame(['billing', 'invoices'], $content['items'][0]['tags']);
        self::assertSame('failed-final', $content['items'][0]['currentStage']);
        self::assertSame(1, $content['items'][0]['attempts']);
    }

    public function testListFilteredByMonitoringId(): void
    {
        $client = self::createClient();
        $this->repository()->save(self::occurrence(
            '8b4e8ee1-2952-49aa-bddc-3a909b933185',
            'App\Message\SendInvoice',
            'INV-123',
            null,
            [],
            new \DateTimeImmutable('2026-11-17T20:41:03.123+01:00'),
        ));

        $client->request('GET', '/api/message-occurrences?monitoringId=INV-123');
        $content = self::content($client);

        self::assertSame(1, $content['totalItems']);
        self::assertSame('8b4e8ee1-2952-49aa-bddc-3a909b933185', $content['items'][0]['occurrenceId']);
    }

    public function testListRejectedForUnknownFilterParameter(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/message-occurrences?userName=john');

        self::assertResponseStatusCodeSame(400);
    }

    public function testPageOutOfRangeIsStillFine(): void
    {
        $client = self::createClient();
        $this->repository()->save(self::occurrence(
            '8b4e8ee1-2952-49aa-bddc-3a909b933185',
            'App\Message\SendInvoice',
            null, null, [],
            new \DateTimeImmutable('2026-11-17T20:41:03.123+01:00'),
        ));

        $client->request('GET', '/api/message-occurrences?page=2&itemsPerPage=2');
        $content = self::content($client);

        self::assertSame([], $content['items']);
        self::assertSame(1, $content['totalItems']);
        self::assertSame(1, $content['totalPages']);
    }

    public function testItemWithDerivedFields(): void
    {
        $client = self::createClient();
        $this->repository()->save(self::occurrence(
            '8b4e8ee1-2952-49aa-bddc-3a909b933185',
            'App\Message\SendInvoice',
            'INV-123', 'ORD-99', ['billing'],
            new \DateTimeImmutable('2026-11-17T20:41:03.123+01:00'),
        ));

        $client->request('GET', '/api/message-occurrences/8b4e8ee1-2952-49aa-bddc-3a909b933185');

        self::assertResponseStatusCodeSame(200);
        $content = self::content($client);

        self::assertSame('App\Message\SendInvoice', $content['className']);
        self::assertSame('failed-final', $content['currentStage']);
        self::assertFalse($content['hasPendingRetry']);
        self::assertNotNull($content['finishedAt']);
        self::assertSame('2026-11-17T20:41:03.123000+01:00', $content['lastOccurredAt']);
    }

    public function testMissingItemIsNotFound(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/message-occurrences/00000000-0000-4000-8000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }

    public function testStatusesSubCollectionSortedAsc(): void
    {
        $client = self::createClient();
        $repository = $this->repository();
        $domain = self::occurrence(
            '8b4e8ee1-2952-49aa-bddc-3a909b933185',
            'App\Message\SendInvoice', null, null, [],
            new \DateTimeImmutable('2026-11-17T20:41:09.999+01:00'),
        );
        $domain->append(new OccurrenceStatus(Stage::Sent, new \DateTimeImmutable('2026-11-17T20:41:03.123+01:00'), 'billing'));
        $repository->save($domain);

        $client->request('GET', '/api/message-occurrences/8b4e8ee1-2952-49aa-bddc-3a909b933185/statuses');

        self::assertResponseStatusCodeSame(200);
        $content = self::contentList($client);

        self::assertCount(2, $content);
        self::assertSame('sent', $content[0]['stage']);
        self::assertSame('failed-final', $content[1]['stage']);
        self::assertSame('2026-11-17T20:41:03.123000+01:00', $content[0]['occurredAt']);
        self::assertNotNull($content[1]['payload'] ?? null);
    }

    /** @return array<string, mixed> */
    private static function content(KernelBrowser $client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return list<array<string, mixed>> */
    private static function contentList(KernelBrowser $client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function repository(): InMemoryMessageOccurrenceRepository
    {
        $repository = static::getContainer()->get(InMemoryMessageOccurrenceRepository::class);
        \assert($repository instanceof InMemoryMessageOccurrenceRepository);

        return $repository;
    }

    /** @param list<string> $tags */
    private static function occurrence(
        string $id,
        string $className,
        ?string $monitoringId,
        ?string $processId,
        array $tags,
        \DateTimeImmutable $occurredAt,
    ): OccurrenceAggregate {
        $aggregate = new OccurrenceAggregate($id, $className);
        $aggregate->fillMetadata($monitoringId, $processId, null, $tags);
        $aggregate->append(new OccurrenceStatus(Stage::FailedFinal, $occurredAt, 'billing', environment: 'prod', payload: '{"ok":true}'));

        return $aggregate;
    }
}
