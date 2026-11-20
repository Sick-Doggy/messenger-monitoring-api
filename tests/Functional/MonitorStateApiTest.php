<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\ApiResource\Monitor\Document\Monitor;
use App\ApiResource\MonitorState\Document\MonitorState;
use App\ApiResource\MonitorState\Document\Stage;
use Doctrine\ODM\MongoDB\DocumentManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class MonitorStateApiTest extends WebTestCase
{
    private const string OCCURRENCE_ID = '6b2f4a1e-9c3d-4f0e-8f7a-1b2c3d4e5f60';
    private const string OTHER_OCCURRENCE_ID = 'a4c1d9e2-77aa-4b8e-9c3d-5e6f7a8b9c0d';

    private KernelBrowser $client;
    private DocumentManager $documentManager;
    private string $apiKey;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $apiKey = self::getContainer()->getParameter('api_key');
        self::assertIsString($apiKey);
        $this->apiKey = $apiKey;

        $documentManager = self::getContainer()->get(DocumentManager::class);
        \assert($documentManager instanceof DocumentManager);
        $this->documentManager = $documentManager;

        $this->dropCollections();
        // unikalny indeks klucza naturalnego (z mapowania dokumentu)
        $this->documentManager->getSchemaManager()->ensureDocumentIndexes(MonitorState::class);

        $this->createMonitor(self::OCCURRENCE_ID);
    }

    protected function tearDown(): void
    {
        $this->dropCollections();
        parent::tearDown();
    }

    public function testCreateState(): void
    {
        $this->requestJson('POST', $this->statesUri(), [
            'stage' => 'failed-retry',
            'occurredAt' => '2026-11-20T10:00:00.470123+00:00',
            'application' => 'billing',
            'environment' => 'prod',
            'busName' => 'messenger.bus.default',
            'transportName' => 'async',
            'handlerName' => 'App\MessageHandler\SendInvoiceHandler',
            'errorClass' => 'RuntimeException',
            'errorMessage' => 'Timeout',
        ]);

        self::assertResponseStatusCodeSame(201);

        $data = $this->decodedResponse();
        self::assertTrue(Uuid::isValid((string) ($data['id'] ?? '')), 'id nadaje serwer (uuid)');
        self::assertSame(self::OCCURRENCE_ID, $data['occurrenceId'] ?? null);
        self::assertSame('failed-retry', $data['stage'] ?? null);
        self::assertSame('2026-11-20T10:00:00.470123+00:00', $data['occurredAt'] ?? null);
        self::assertSame('billing', $data['application'] ?? null);
        self::assertSame('prod', $data['environment'] ?? null);
        self::assertSame('messenger.bus.default', $data['busName'] ?? null);
        self::assertSame('async', $data['transportName'] ?? null);
        self::assertSame('App\MessageHandler\SendInvoiceHandler', $data['handlerName'] ?? null);
        self::assertSame('RuntimeException', $data['errorClass'] ?? null);
        self::assertSame('Timeout', $data['errorMessage'] ?? null);

        $this->documentManager->clear();
        $stored = $this->documentManager->find(MonitorState::class, $data['id']);
        self::assertInstanceOf(MonitorState::class, $stored);
        self::assertSame(Stage::FailedRetry, $stored->getStage());
    }

    public function testCreatingSameStateAgainReturnsExistingOne(): void
    {
        $first = $this->postState('sent', '2026-11-20T10:00:00.100000+00:00');
        $second = $this->postState('sent', '2026-11-20T10:00:00.100000+00:00');

        self::assertResponseStatusCodeSame(201);
        self::assertSame($first, $second);
        self::assertSame(1, $this->stateCount());
    }

    public function testCreateForMissingMonitorReturns404(): void
    {
        $this->requestJson('POST', $this->statesUri(self::OTHER_OCCURRENCE_ID), $this->statePayload('sent', '2026-11-20T10:00:00.100000+00:00'));

        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->stateCount());
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidPayloads')]
    public function testInvalidStateReturns422(array $override): void
    {
        $payload = array_merge($this->statePayload('sent', '2026-11-20T10:00:00.100000+00:00'), $override);
        $this->requestJson('POST', $this->statesUri(), $payload);

        self::assertResponseStatusCodeSame(422);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidPayloads(): iterable
    {
        yield 'nieznany stage' => [['stage' => 'lost']];
        yield 'brak stage' => [['stage' => '']];
        yield 'brak application' => [['application' => '']];
        yield 'occurredAt nie RFC3339' => [['occurredAt' => '20.11.2026 10:00']];
        yield 'brak occurredAt' => [['occurredAt' => '']];
        yield 'occurredAt miesiąc 13' => [['occurredAt' => '2026-13-45T10:00:00+00:00']];
        yield 'occurredAt godzina 25' => [['occurredAt' => '2026-11-20T25:61:00+00:00']];
        yield 'occurredAt 30 lutego' => [['occurredAt' => '2026-02-30T10:00:00+00:00']];
        yield 'occurredAt strefa bez dwukropka' => [['occurredAt' => '2026-11-20T10:00:00+0100']];
    }

    public function testPatchWithNonexistentDateReturns422(): void
    {
        $created = $this->postState('sent', '2026-11-20T10:00:00.100000+00:00');

        $this->requestJson('PATCH', $this->stateUri($created), ['occurredAt' => '2026-02-30T10:00:00+00:00']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testFiltersByStageAndApplication(): void
    {
        $this->postState('sent', '2026-11-20T10:00:00.100000+00:00');
        $this->requestJson('POST', $this->statesUri(), ['stage' => 'received', 'occurredAt' => '2026-11-20T10:00:00.300000+00:00', 'application' => 'billing']);
        $this->requestJson('POST', $this->statesUri(), ['stage' => 'failed-retry', 'occurredAt' => '2026-11-20T10:00:00.400000+00:00', 'application' => 'billing']);
        $this->requestJson('POST', $this->statesUri(), ['stage' => 'failed-final', 'occurredAt' => '2026-11-20T10:00:00.800000+00:00', 'application' => 'billing']);

        $this->requestApi('GET', $this->statesUri().'?stage=failed-retry');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['failed-retry'], $this->memberStages());

        $this->requestApi('GET', $this->statesUri().'?stage[]=failed-retry&stage[]=failed-final');
        self::assertSame(['failed-retry', 'failed-final'], $this->memberStages(), 'wiele etapów = dowolny z nich (OR)');
        self::assertSame(2, $this->decodedResponse()['totalItems'] ?? null);

        $this->requestApi('GET', $this->statesUri().'?stage=sent,failed-final');
        self::assertSame(['sent', 'failed-final'], $this->memberStages());

        $this->requestApi('GET', $this->statesUri().'?application=billing');
        self::assertSame(['received', 'failed-retry', 'failed-final'], $this->memberStages());

        $this->requestApi('GET', $this->statesUri().'?application=billing&stage=sent');
        self::assertSame([], $this->memberStages());
    }

    public function testUnknownStageFilterReturnsNothingInsteadOfWholeHistory(): void
    {
        $this->postState('sent', '2026-11-20T10:00:00.100000+00:00');

        $this->requestApi('GET', $this->statesUri().'?stage=lost');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(0, $this->decodedResponse()['totalItems'] ?? null);
    }

    public function testRequestWithoutApiKeyReturns401(): void
    {
        $this->client->request('GET', $this->statesUri());
        self::assertResponseStatusCodeSame(401);

        $this->client->request(
            'POST',
            $this->statesUri(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->statePayload('sent', '2026-11-20T10:00:00.100000+00:00'), JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(401);
        self::assertSame(0, $this->stateCount());
    }

    public function testListIsChronologicalAndPaginated(): void
    {
        // raporty dochodzą w innej kolejności, niż zdarzenia nastąpiły
        $this->postState('handled', '2026-11-20T10:00:00.900000+00:00');
        $this->postState('sent', '2026-11-20T10:00:00.100000+00:00');
        $this->postState('received', '2026-11-20T10:00:00.500000+00:00');

        $this->requestApi('GET', $this->statesUri().'?itemsPerPage=2');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(3, $this->decodedResponse()['totalItems'] ?? null);
        self::assertSame(['sent', 'received'], $this->memberStages());

        $this->requestApi('GET', $this->statesUri().'?itemsPerPage=2&page=2');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['handled'], $this->memberStages());
    }

    public function testListContainsOnlyStatesOfThatMonitor(): void
    {
        $this->createMonitor(self::OTHER_OCCURRENCE_ID);
        $this->postState('sent', '2026-11-20T10:00:00.100000+00:00');
        $this->requestJson('POST', $this->statesUri(self::OTHER_OCCURRENCE_ID), $this->statePayload('handled', '2026-11-20T10:00:00.900000+00:00'));

        $this->requestApi('GET', $this->statesUri());

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['sent'], $this->memberStages());
    }

    public function testListForMissingMonitorReturns404(): void
    {
        $this->requestApi('GET', $this->statesUri(self::OTHER_OCCURRENCE_ID));

        self::assertResponseStatusCodeSame(404);
    }

    public function testGetState(): void
    {
        $created = $this->postState('sent', '2026-11-20T10:00:00.100000+00:00');

        $this->requestApi('GET', $this->stateUri($created));

        self::assertResponseStatusCodeSame(200);
        self::assertSame($created, $this->decodedResponse()['id'] ?? null);
        self::assertSame('sent', $this->decodedResponse()['stage'] ?? null);
    }

    public function testStateIsNotReachableThroughAnotherMonitor(): void
    {
        $this->createMonitor(self::OTHER_OCCURRENCE_ID);
        $created = $this->postState('sent', '2026-11-20T10:00:00.100000+00:00');

        $this->requestApi('GET', $this->stateUri($created, self::OTHER_OCCURRENCE_ID));
        self::assertResponseStatusCodeSame(404);

        $this->requestApi('DELETE', $this->stateUri($created, self::OTHER_OCCURRENCE_ID));
        self::assertResponseStatusCodeSame(404);
        self::assertSame(1, $this->stateCount());
    }

    public function testPutReplacesState(): void
    {
        $created = $this->postState('sent', '2026-11-20T10:00:00.100000+00:00', ['transportName' => 'async']);

        $this->requestJson('PUT', $this->stateUri($created), [
            'stage' => 'send-failed',
            'occurredAt' => '2026-11-20T10:00:00.200000+00:00',
            'application' => 'shop',
            'errorClass' => 'RuntimeException',
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = $this->decodedResponse();
        self::assertSame($created, $data['id'] ?? null);
        self::assertSame('send-failed', $data['stage'] ?? null);
        self::assertSame('2026-11-20T10:00:00.200000+00:00', $data['occurredAt'] ?? null);
        self::assertSame('RuntimeException', $data['errorClass'] ?? null);
        self::assertArrayNotHasKey('transportName', $data, 'PUT nadpisuje całość — pominięte pole znika');
    }

    public function testPatchChangesOnlyProvidedFields(): void
    {
        $created = $this->postState('sent', '2026-11-20T10:00:00.100000+00:00', ['transportName' => 'async']);

        $this->requestJson('PATCH', $this->stateUri($created), ['handlerName' => 'App\MessageHandler\SendInvoiceHandler']);

        self::assertResponseStatusCodeSame(200);
        $data = $this->decodedResponse();
        self::assertSame('sent', $data['stage'] ?? null);
        self::assertSame('async', $data['transportName'] ?? null);
        self::assertSame('App\MessageHandler\SendInvoiceHandler', $data['handlerName'] ?? null);

        $this->documentManager->clear();
        $stored = $this->documentManager->find(MonitorState::class, $created);
        self::assertInstanceOf(MonitorState::class, $stored);
        self::assertSame('App\MessageHandler\SendInvoiceHandler', $stored->getHandlerName());
    }

    public function testChangeIntoDuplicateOfAnotherStateReturns409(): void
    {
        $this->postState('sent', '2026-11-20T10:00:00.100000+00:00');
        $other = $this->postState('received', '2026-11-20T10:00:00.500000+00:00');

        $this->requestJson('PATCH', $this->stateUri($other), ['stage' => 'sent', 'occurredAt' => '2026-11-20T10:00:00.100000+00:00']);
        self::assertResponseStatusCodeSame(409);

        $this->requestJson('PUT', $this->stateUri($other), $this->statePayload('sent', '2026-11-20T10:00:00.100000+00:00'));
        self::assertResponseStatusCodeSame(409);

        $this->documentManager->clear();
        $stored = $this->documentManager->find(MonitorState::class, $other);
        self::assertInstanceOf(MonitorState::class, $stored);
        self::assertSame(Stage::Received, $stored->getStage(), '409 nie może zmienić wpisu');
    }

    public function testMissingStateReturns404(): void
    {
        $missing = Uuid::v4()->toRfc4122();

        $this->requestApi('GET', $this->stateUri($missing));
        self::assertResponseStatusCodeSame(404);

        $this->requestJson('PATCH', $this->stateUri($missing), ['stage' => 'sent']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteState(): void
    {
        $created = $this->postState('sent', '2026-11-20T10:00:00.100000+00:00');

        $this->requestApi('DELETE', $this->stateUri($created));

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->stateCount());
    }

    public function testDeletingMonitorDeletesItsStates(): void
    {
        $this->createMonitor(self::OTHER_OCCURRENCE_ID);
        $this->postState('sent', '2026-11-20T10:00:00.100000+00:00');
        $this->postState('handled', '2026-11-20T10:00:00.900000+00:00');
        $this->requestJson('POST', $this->statesUri(self::OTHER_OCCURRENCE_ID), $this->statePayload('sent', '2026-11-20T10:00:00.100000+00:00'));

        $this->requestApi('DELETE', '/api/monitors/'.self::OCCURRENCE_ID);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->stateCount(), 'zostaje tylko stan drugiego wystąpienia');
    }

    public function testUniqueIndexRejectsDuplicateWrittenAroundTheApi(): void
    {
        $this->documentManager->persist(new MonitorState(self::OCCURRENCE_ID, Stage::Sent, '2026-11-20T10:00:00.100000+00:00', 'shop'));
        $this->documentManager->persist(new MonitorState(self::OCCURRENCE_ID, Stage::Sent, '2026-11-20T10:00:00.100000+00:00', 'shop'));

        $this->expectException(\MongoDB\Driver\Exception\BulkWriteException::class);

        $this->documentManager->flush();
    }

    private function createMonitor(string $occurrenceId): void
    {
        $this->documentManager->persist(new Monitor($occurrenceId, 'App\Message\SendInvoice'));
        $this->documentManager->flush();
        $this->documentManager->clear();
    }

    /**
     * @param array<string, string> $extra
     *
     * @return string id utworzonego stanu
     */
    private function postState(string $stage, string $occurredAt, array $extra = []): string
    {
        $this->requestJson('POST', $this->statesUri(), array_merge($this->statePayload($stage, $occurredAt), $extra));
        self::assertResponseStatusCodeSame(201);

        $id = $this->decodedResponse()['id'] ?? null;
        \assert(\is_string($id));

        return $id;
    }

    /**
     * @return array<string, string>
     */
    private function statePayload(string $stage, string $occurredAt): array
    {
        return ['stage' => $stage, 'occurredAt' => $occurredAt, 'application' => 'shop'];
    }

    private function statesUri(string $occurrenceId = self::OCCURRENCE_ID): string
    {
        return '/api/monitors/'.$occurrenceId.'/states';
    }

    private function stateUri(string $id, string $occurrenceId = self::OCCURRENCE_ID): string
    {
        return $this->statesUri($occurrenceId).'/'.$id;
    }

    /** @return list<string> */
    private function memberStages(): array
    {
        $member = $this->decodedResponse()['member'] ?? [];
        \assert(\is_array($member));

        $stages = [];
        foreach ($member as $item) {
            \assert(\is_array($item) && \is_string($item['stage'] ?? null));
            $stages[] = $item['stage'];
        }

        return $stages;
    }

    private function stateCount(): int
    {
        return $this->documentManager->getDocumentCollection(MonitorState::class)->countDocuments();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requestJson(string $method, string $uri, array $payload): void
    {
        // PATCH działa jako merge-patch (RFC 7386) — inny Content-Type niż POST/PUT
        $contentType = 'PATCH' === $method ? 'application/merge-patch+json' : 'application/json';

        $this->requestApi($method, $uri, ['CONTENT_TYPE' => $contentType], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $server
     */
    private function requestApi(string $method, string $uri, array $server = [], ?string $content = null): void
    {
        $this->client->request($method, $uri, server: $server + ['HTTP_X_API_KEY' => $this->apiKey], content: $content);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedResponse(): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    private function dropCollections(): void
    {
        $this->documentManager->getDocumentCollection(Monitor::class)->drop();
        $this->documentManager->getDocumentCollection(MonitorState::class)->drop();
        $this->documentManager->clear();
    }
}
