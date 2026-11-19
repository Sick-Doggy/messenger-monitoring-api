<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\ApiResource\Monitor\Document\Monitor;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MonitorApiTest extends WebTestCase
{
    /** uuid v4 (RFC4122) — wersja 4 jest zakodowana w trzeciej grupie. */
    private const string UUID_V4 = '6b2f4a1e-9c3d-4f0e-8f7a-1b2c3d4e5f60';
    private const string UUID_V4_ALT = 'a4c1d9e2-77aa-4b8e-9c3d-5e6f7a8b9c0d';
    private const string ENDPOINT = '/api/monitors';
    private const string CLASS_NAME = 'App\Message\SendInvoice';

    private KernelBrowser $client;
    private DocumentManager $documentManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $documentManager = self::getContainer()->get(DocumentManager::class);
        \assert($documentManager instanceof DocumentManager);
        $this->documentManager = $documentManager;

        $this->dropCollection();
    }

    protected function tearDown(): void
    {
        $this->dropCollection();
        parent::tearDown();
    }

    public function testCreateOccurrence(): void
    {
        $this->requestJson('POST', self::ENDPOINT, [
            'occurrenceId' => self::UUID_V4,
            'className' => self::CLASS_NAME,
            'monitoringId' => 'invoiceId-42',
            'processId' => 'spring-cleaning-2026',
            'name' => 'Faktury listopad',
            'tags' => ['billing', 'urgent'],
        ]);

        self::assertResponseStatusCodeSame(201);

        $data = $this->decodedResponse();
        self::assertSame(self::UUID_V4, $data['occurrenceId'] ?? null);
        self::assertSame(self::CLASS_NAME, $data['className'] ?? null);
        self::assertSame('invoiceId-42', $data['monitoringId'] ?? null);
        self::assertSame('billing', $data['tags'][0] ?? null, 'tags muszą być wielowartościową listą');
    }

    public function testSameOccurrenceIdUpsertsInsteadOfDuplicating(): void
    {
        $this->requestJson('POST', self::ENDPOINT, [
            'occurrenceId' => self::UUID_V4,
            'className' => self::CLASS_NAME,
            'name' => 'first',
        ]);
        self::assertResponseStatusCodeSame(201);

        $this->requestJson('POST', self::ENDPOINT, [
            'occurrenceId' => self::UUID_V4,
            'className' => self::CLASS_NAME,
            'name' => 'second',
            'tags' => ['retried'],
        ]);
        self::assertResponseStatusCodeSame(201);

        $this->documentManager->clear();

        $collection = $this->documentManager->getDocumentCollection(Monitor::class);
        self::assertSame(1, $collection->countDocuments(), 'ten sam occurrenceId nie może duplikować dokumentu');

        $stored = $this->documentManager->find(Monitor::class, self::UUID_V4);
        \assert($stored instanceof Monitor);
        self::assertSame('second', $stored->getName());
        self::assertSame(['retried'], $stored->getTags());
    }

    public function testMissingClassNameReturns422(): void
    {
        $this->requestJson('POST', self::ENDPOINT, [
            'occurrenceId' => self::UUID_V4,
            // brak className → DTO default '' → 422
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testNonUuidOccurrenceIdReturns422(): void
    {
        $this->requestJson('POST', self::ENDPOINT, [
            'occurrenceId' => 'not-a-uuid',
            'className' => self::CLASS_NAME,
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testUuidV1OccurrenceIdReturns422(): void
    {
        // uuid v1 — wersja w trzeciej grupie zaczyna się od 1
        $this->requestJson('POST', self::ENDPOINT, [
            'occurrenceId' => '3d4f5a6b-7c8d-1e9f-2a3b-4c5d6e7f8a9b',
            'className' => self::CLASS_NAME,
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testClassNameThatIsNotFqcnReturns422(): void
    {
        $this->requestJson('POST', self::ENDPOINT, [
            'occurrenceId' => self::UUID_V4,
            'className' => 'SendInvoice without namespace',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testPatchChangesOnlyProvidedFields(): void
    {
        $this->createDocument();
        \assert(null !== $this->documentManager->find(Monitor::class, self::UUID_V4));

        $this->requestJson('PATCH', self::ENDPOINT.'/'.self::UUID_V4, [
            'name' => 'renamed-occurrence',
        ]);

        self::assertResponseStatusCodeSame(200);

        $data = $this->decodedResponse();
        self::assertSame('renamed-occurrence', $data['name'] ?? null);
        self::assertSame('invoiceId-42', $data['monitoringId'] ?? null);

        $this->documentManager->clear();
        $stored = $this->documentManager->find(Monitor::class, self::UUID_V4);
        \assert($stored instanceof Monitor);
        self::assertSame(self::CLASS_NAME, $stored->getClassName());
        self::assertSame('renamed-occurrence', $stored->getName());
    }

    public function testPatchWithEmptyTagsClearsTags(): void
    {
        $this->createDocument();

        $this->requestJson('PATCH', self::ENDPOINT.'/'.self::UUID_V4, ['tags' => []]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $this->decodedResponse()['tags'] ?? null);
    }

    public function testFiltersByProcessIdMonitoringIdAndTags(): void
    {
        $this->createDocument(); // monitoringId=invoiceId-42, processId=spring-cleaning-2026, tags=[billing, urgent]

        $other = new Monitor(self::UUID_V4_ALT, 'App\Message\SendReminder', 'reminderId-7', 'spring-cleaning-2026', null, ['reminder']);
        $this->documentManager->persist($other);
        $this->documentManager->flush();

        $this->client->request('GET', self::ENDPOINT.'?processId=spring-cleaning-2026');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(2, $this->decodedResponse()['totalItems'] ?? null);

        $this->client->request('GET', self::ENDPOINT.'?monitoringId=invoiceId-42');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, $this->decodedResponse()['totalItems'] ?? null);

        $this->client->request('GET', self::ENDPOINT.'?tags[]=billing&tags[]=urgent');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, $this->decodedResponse()['totalItems'] ?? null);

        $this->client->request('GET', self::ENDPOINT.'?tags=billing,reminder');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(0, $this->decodedResponse()['totalItems'] ?? null, 'wielowartościowy filtr tagów to AND (wszystkie tagi)');
    }

    public function testItemsPerPageLimitsPageSize(): void
    {
        $monitor = new Monitor(self::UUID_V4, self::CLASS_NAME, 'invoiceId-42', null, null, []);
        $other = new Monitor(self::UUID_V4_ALT, self::CLASS_NAME, null, null, null, []);
        $this->documentManager->persist($monitor);
        $this->documentManager->persist($other);
        $this->documentManager->flush();

        $this->client->request('GET', self::ENDPOINT.'?itemsPerPage=1');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(2, $this->decodedResponse()['totalItems'] ?? null);
        self::assertCount(1, $this->decodedResponse()['member'] ?? []);
    }

    public function testDeleteRemovesDocument(): void
    {
        $this->createDocument();

        $this->client->request('DELETE', self::ENDPOINT.'/'.self::UUID_V4);

        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->documentManager->find(Monitor::class, self::UUID_V4));
    }

    public function testGetMissingOccurrenceReturns404(): void
    {
        $this->client->request('GET', self::ENDPOINT.'/'.self::UUID_V4);

        self::assertResponseStatusCodeSame(404);
    }

    private function createDocument(): void
    {
        $monitor = new Monitor(
            occurrenceId: self::UUID_V4,
            className: self::CLASS_NAME,
            monitoringId: 'invoiceId-42',
            processId: 'spring-cleaning-2026',
            name: 'Faktury listopad',
            tags: ['billing', 'urgent'],
        );
        $this->documentManager->persist($monitor);
        $this->documentManager->flush();
        $this->documentManager->clear();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requestJson(string $method, string $uri, array $payload): void
    {
        // PATCH działa jako merge-patch (RFC 7386) — inny Content-Type niż POST/PUT
        $contentType = 'PATCH' === $method ? 'application/merge-patch+json' : 'application/json';

        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => $contentType],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedResponse(): array
    {
        $content = (string) $this->client->getResponse()->getContent();

        /** @var array<string, mixed> $data */
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    private function dropCollection(): void
    {
        $this->documentManager->getDocumentCollection(Monitor::class)->drop();
        $this->documentManager->clear();
    }
}
