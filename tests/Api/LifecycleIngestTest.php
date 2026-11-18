<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Persistence\InMemoryMessageOccurrenceRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LifecycleIngestTest extends WebTestCase
{
    private const INGEST_HEADERS = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_API_KEY' => 'test-key',
    ];

    public function testFirstReportCreatesHeader(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->request('POST', '/api/lifecycle-reports', [], [], self::INGEST_HEADERS, self::validReport());

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Content-Type', 'application/json; charset=utf-8');

        $content = self::content($client);
        self::assertTrue($content['created']);
        self::assertSame(1, $content['acceptedStates']);
        self::assertSame(0, $content['skippedDuplicates']);

        $repository = $this->repository();
        $domain = $repository->get('8b4e8ee1-2952-49aa-bddc-3a909b933185');
        self::assertNotNull($domain);
        self::assertSame('INV-123', $domain->monitoringId());
    }

    public function testDuplicateReportIsIdempotent(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->request('POST', '/api/lifecycle-reports', [], [], self::INGEST_HEADERS, self::validReport());
        $client->request('POST', '/api/lifecycle-reports', [], [], self::INGEST_HEADERS, self::validReport());

        self::assertResponseStatusCodeSame(200);
        $content = self::content($client);

        self::assertFalse($content['created']);
        self::assertSame(0, $content['acceptedStates']);
        self::assertSame(1, $content['skippedDuplicates']);

        $domain = $this->repository()->get('8b4e8ee1-2952-49aa-bddc-3a909b933185');
        self::assertNotNull($domain);
        self::assertCount(1, $domain->statuses());
    }

    public function testNewStatusAppendsToExistingHeader(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->request('POST', '/api/lifecycle-reports', [], [], self::INGEST_HEADERS, self::validReport());
        $client->request('POST', '/api/lifecycle-reports', [], [], self::INGEST_HEADERS, self::validReport('handled'));

        self::assertResponseStatusCodeSame(200);
        $content = self::content($client);

        self::assertFalse($content['created']);
        self::assertSame(1, $content['acceptedStates']);

        $domain = $this->repository()->get('8b4e8ee1-2952-49aa-bddc-3a909b933185');
        self::assertCount(2, $domain?->statuses() ?? []);
    }

    public function testMetadataNotOverwrittenBySecondReport(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->request('POST', '/api/lifecycle-reports', [], [], self::INGEST_HEADERS, self::validReport());

        $second = json_decode(self::validReport('received'), false, 512, JSON_THROW_ON_ERROR);
        $second->monitoringId = 'OTHER';
        $second->tags = ['surprise'];

        $client->request('POST', '/api/lifecycle-reports', [], [], self::INGEST_HEADERS, (string) json_encode($second, JSON_THROW_ON_ERROR));

        $domain = $this->repository()->get('8b4e8ee1-2952-49aa-bddc-3a909b933185');
        self::assertNotNull($domain);
        self::assertSame('INV-123', $domain->monitoringId());
        self::assertSame(['billing', 'invoices'], $domain->tags());
    }

    public function testMissingApiKeyIsRejected(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->request('POST', '/api/lifecycle-reports', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], self::validReport());

        self::assertResponseStatusCodeSame(401);
    }

    public function testWrongApiKeyIsRejected(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->request('POST', '/api/lifecycle-reports', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_KEY' => 'nope',
        ], self::validReport());

        self::assertResponseStatusCodeSame(401);
    }

    public function testInvalidStageIsRejected(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->request('POST', '/api/lifecycle-reports', [], [], self::INGEST_HEADERS, self::validReport('definitely-not-a-stage'));

        self::assertResponseStatusCodeSame(422);
    }

    public function testReportWithoutStatusesIsRejected(): void
    {
        $body = json_decode(self::validReport(), false, 512, JSON_THROW_ON_ERROR);
        $body->statuses = [];

        $client = self::createClient();
        $client->disableReboot();
        $client->request('POST', '/api/lifecycle-reports', [], [], self::INGEST_HEADERS, (string) json_encode($body, JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testInvalidOccurrenceIdIsRejected(): void
    {
        $body = json_decode(self::validReport(), false, 512, JSON_THROW_ON_ERROR);
        $body->occurrenceId = 'not-a-uuid';

        $client = self::createClient();
        $client->disableReboot();
        $client->request('POST', '/api/lifecycle-reports', [], [], self::INGEST_HEADERS, (string) json_encode($body, JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testInvalidOccurredAtIsRejected(): void
    {
        $body = json_decode(self::validReport(), false, 512, JSON_THROW_ON_ERROR);
        $body->statuses[0]->occurredAt = 'not-a-date';

        $client = self::createClient();
        $client->disableReboot();
        $client->request('POST', '/api/lifecycle-reports', [], [], self::INGEST_HEADERS, (string) json_encode($body, JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testInvalidPayloadJsonIsRejected(): void
    {
        $body = json_decode(self::validReport(), false, 512, JSON_THROW_ON_ERROR);
        $body->statuses[0]->payload = '{invalid json';

        $client = self::createClient();
        $client->disableReboot();
        $client->request('POST', '/api/lifecycle-reports', [], [], self::INGEST_HEADERS, (string) json_encode($body, JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    /** @return array<string, mixed> */
    private static function content(KernelBrowser $client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function repository(): InMemoryMessageOccurrenceRepository
    {
        $repository = static::getContainer()->get(InMemoryMessageOccurrenceRepository::class);
        \assert($repository instanceof InMemoryMessageOccurrenceRepository);

        return $repository;
    }

    private static function validReport(string $stage = 'sent'): string
    {
        return (string) json_encode([
            'occurrenceId' => '8b4e8ee1-2952-49aa-bddc-3a909b933185',
            'className' => 'App\Message\SendInvoice',
            'monitoringId' => 'INV-123',
            'processId' => 'ORD-99',
            'name' => 'Wystawienie faktury',
            'tags' => ['billing', 'invoices'],
            'statuses' => [
                [
                    'stage' => $stage,
                    'occurredAt' => '2026-11-17T20:41:03.123+01:00',
                    'application' => 'billing',
                    'environment' => 'prod',
                    'busName' => 'messenger.bus.default',
                    'transportName' => 'sent' === $stage ? 'async' : null,
                    'payload' => (string) json_encode(['invoiceId' => 'INV-123'], JSON_THROW_ON_ERROR),
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
