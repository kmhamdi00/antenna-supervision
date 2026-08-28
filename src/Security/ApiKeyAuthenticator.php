<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authentification simple par clé API statique, transmise via l'en-tête
 * `X-API-KEY`.
 */
class ApiKeyAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const HEADER = 'X-API-KEY';

    public function __construct(private readonly string $expectedApiKey) {}

    public function supports(Request $request): ?bool
    {
        return $request->headers->has(self::HEADER);
    }

    public function authenticate(Request $request): Passport
    {
        $apiKey = $request->headers->get(self::HEADER);

        if (empty($apiKey) || !hash_equals($this->expectedApiKey, $apiKey)) {
            throw new CustomUserMessageAuthenticationException('Invalid API key.');
        }

        return new SelfValidatingPassport(new UserBadge('api-client', fn() => new ApiKeyUser()));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['error' => ['code' => 'UNAUTHENTICATED', 'message' => $exception->getMessage()]],
            Response::HTTP_UNAUTHORIZED
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // laisse la requête continuer vers le contrôleur
    }

    /**
     * Appelée quand la route exige une authentification mais qu'aucun en-tête
     * X-API-KEY n'a été envoyé du tout (supports() a renvoyé false).
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(
            ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Missing X-API-KEY header.']],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
