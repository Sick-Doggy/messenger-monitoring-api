<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Api\ApiTestCase;

/**
 * Testy kontraktowe, które nie wymagają Mongo: autoryzacja i walidacja.
 * Scenariusze z zapisem (201/200, odczyty) są weryfikowane przeciwko prawdziwej bazie w docker-compose.
 */
final class IngestContractTest extends ApiTestCase
{
    public function testMissingApiKeyIs401(): void
    {
        $client = self::createClient();
        $client->request('POST', '/api/lifecycle-reports', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $this->validReport());

        self::assertResponseStatusCodeSame(401);
    }

    public function testWrongApiKeyIs401(): void
    {
        $client = self::createClient();
        $client->request('POST', '/api/lifecycle-reports', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_KEY' => 'nope',
        ], $this->validReport());

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnknownStageIs422(): void
    {
        $client = self::createClient();
        $client->request('POST', '/api/lifecycle-reports', [], [], self::headers(), $this->validReport('definitely-not-a-stage'));

        self::assertResponseStatusCodeSame(422);
    }

    public function testReportWithoutStatusesIs422(): void
    {
        $body = json_decode($this->validReport(), false, 512, JSON_THROW_ON_ERROR);
        $body->statuses = [];

        $client = self::createClient();
        $client->request('POST', '/api/lifecycle-reports', [], [], self::headers(), (string) json_encode($body, JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testInvalidOccurredAtIs422(): void
    {
        $body = json_decode($this->validReport(), false, 512, JSON_THROW_ON_ERROR);
        $body->statuses[0]->occurredAt = 'not-a-date';

        $client = self::createClient();
        $client->request('POST', '/api/lifecycle-reports', [], [], self::headers(), (string) json_encode($body, JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testInvalidPayloadJsonIs422(): void
    {
        $body = json_decode($this->validReport(), false, 512, JSON_THROW_ON_ERROR);
        $body->statuses[0]->payload = '{invalid json';

        $client = self::createClient();
        $client->request('POST', '/api/lifecycle-reports', [], [], self::headers(), (string) json_encode($body, JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testInvalidOccurrenceIdIs422(): void
    {
        $body = json_decode($this->validReport(), false, 512, JSON_THROW_ON_ERROR);
        $body->occurrenceId = 'not-a-uuid';

        $client = self::createClient();
        $client->request('POST', '/api/lifecycle-reports', [], [], self::headers(), (string) json_encode($body, JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    /** @return array<string, string> */
    private static function headers(): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_KEY' => 'test-key',
        ];
    }

    private function validReport(string $stage = 'sent'): string
    {
        return (string) json_encode([
            'occurrenceId' => '8b4e8ee1-2952-49aa-bddc-3a909b933185',
            'className' => 'App\Message\SendInvoice',
            'monitoringId' => 'INV-123',
            'processId' => 'ORD-99',
            'name' => 'Wystawienie faktury',
            'tags' => ['billing', 'invoices'],
            'statuses' => [[
                'stage' => $stage,
                'occurredAt' => '2026-11-17T20:41:03.123+01:00',
                'application' => 'billing',
                'environment' => 'prod',
                'busName' => 'messenger.bus.default',
                'transportName' => 'async',
                'payload' => '{"invoiceId":"INV-123"}',
            ]],
        ], JSON_THROW_ON_ERROR);
    }
}
