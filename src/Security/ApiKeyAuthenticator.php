<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Autentykacja nagłówkiem X-Api-Key (stały klucz z env API_KEY).
 *
 * Brak nagłówka → nie obsługujemy żądania (decyduje access_control → entry point 401);
 * błędny klucz → BadCredentialsException → onAuthenticationFailure 401.
 */
final class ApiKeyAuthenticator implements AuthenticatorInterface, AuthenticationEntryPointInterface
{
    public const HEADER = 'X-Api-Key';

    public function __construct(
        #[Autowire(param: 'api_key')]
        private readonly string $apiKey,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->problem('Wymagany nagłówek '.self::HEADER.'.');
    }

    public function supports(Request $request): ?bool // @phpstan-ignore return.unusedType (sygnatura AuthenticatorInterface wymaga ?bool)
    {
        return $request->headers->has(self::HEADER);
    }

    public function authenticate(Request $request): Passport
    {
        $provided = (string) $request->headers->get(self::HEADER);

        if (!hash_equals($this->apiKey, $provided)) {
            throw new BadCredentialsException('Błędny klucz API.');
        }

        return new SelfValidatingPassport(new UserBadge('api', static fn (): ApiUser => new ApiUser()));
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        // UserBadge (SelfValidatingPassport) gwarantuje UserInterface
        $user = $passport->getUser();

        return new UsernamePasswordToken($user, $firewallName, $user->getRoles());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response // @phpstan-ignore return.unusedType (sygnatura AuthenticatorInterface wymaga ?Response)
    {
        // bez treści wyjątku (messageKey jest zgeneralizowany, ale celowo go nie przekazujemy)
        return $this->problem('Brak lub błędny klucz API.');
    }

    private function problem(string $detail): JsonResponse
    {
        return new JsonResponse(
            ['status' => 401, 'title' => 'Unauthenticated', 'detail' => $detail],
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => self::HEADER.' realm="Messenger Monitoring API"'],
        );
    }
}
