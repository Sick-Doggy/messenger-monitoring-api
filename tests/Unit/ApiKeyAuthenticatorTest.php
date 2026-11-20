<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Security\ApiKeyAuthenticator;
use App\Security\ApiUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

#[CoversClass(ApiKeyAuthenticator::class)]
final class ApiKeyAuthenticatorTest extends TestCase
{
    public function testSupportsOnlyWhenHeaderPresent(): void
    {
        $authenticator = $this->authenticator();

        self::assertTrue($authenticator->supports($this->request('valid-key')));
        self::assertFalse($authenticator->supports(Request::create('/api/monitors')));
        self::assertSame('X-Api-Key', ApiKeyAuthenticator::HEADER);
    }

    public function testAuthenticateWithValidKeyProvidesApiUser(): void
    {
        $token = $this->authenticator()->authenticate($this->request('valid-key'));

        self::assertInstanceOf(ApiUser::class, $token->getUser());
        self::assertSame(['ROLE_API'], $token->getUser()->getRoles());
    }

    public function testWrongKeyThrowsBadCredentials(): void
    {
        $this->expectException(BadCredentialsException::class);

        $this->authenticator()->authenticate($this->request('totally-wrong'));
    }

    public function testFailureResponseIsProblem401(): void
    {
        $response = $this->authenticator()->onAuthenticationFailure(
            $this->request('totally-wrong'),
            new AuthenticationException(),
        );

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        self::assertSame('X-Api-Key realm="Messenger Monitoring API"', $response->headers->get('WWW-Authenticate'));

        /** @var array{status?: int, detail?: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $body['status'] ?? null);
        self::assertStringNotContainsString('totally-wrong', (string) $response->getContent(), 'Odpowiedź nie może zawierać podanego klucza.');
    }

    public function testEntryPointReturnsProblem401WithoutKey(): void
    {
        $response = $this->authenticator()->start(Request::create('/api/monitors'));

        self::assertInstanceOf(JsonResponse::class, $response);

        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('X-Api-Key', (string) $response->getContent());
    }

    private function authenticator(): ApiKeyAuthenticator
    {
        return new ApiKeyAuthenticator('valid-key');
    }

    private function request(string $key): Request
    {
        return Request::create('/api/monitors', server: ['HTTP_X_API_KEY' => $key]);
    }
}
