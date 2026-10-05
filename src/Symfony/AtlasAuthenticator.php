<?php

declare(strict_types=1);

namespace Atlas\Symfony;

use Atlas\Verify\SessionVerifier;
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

/**
 * Symfony Security authenticator for Atlas sessions.
 *
 * It claims any request carrying a Bearer token or a `__session` cookie, verifies
 * it locally with the framework-free {@see SessionVerifier} (no network on the hot
 * path), and — because the signature is proof enough on its own — returns a
 * {@see SelfValidatingPassport} whose {@see UserBadge} loads an {@see AtlasUser}
 * from the verified `sub`. No credential is re-checked and no user provider is
 * consulted. A missing/invalid token yields a 401.
 */
final class AtlasAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly SessionVerifier $verifier)
    {
    }

    public function supports(Request $request): ?bool
    {
        return self::extractToken($request) !== null;
    }

    public function authenticate(Request $request): Passport
    {
        $token = self::extractToken($request);
        if ($token === null) {
            // supports() gates this, but authenticate() can be reached directly
            // (e.g. an entry point) — fail as an auth error, never a TypeError.
            throw new CustomUserMessageAuthenticationException('No Atlas session token on the request.');
        }

        $result = $this->verifier->verify($token);
        if (!$result->ok) {
            throw new CustomUserMessageAuthenticationException('Invalid Atlas session: ' . (string) $result->reason);
        }

        /** @var array<string,mixed> $claims */
        $claims = $result->claims ?? [];
        $subject = isset($claims['sub']) && is_string($claims['sub']) ? $claims['sub'] : '';
        if ($subject === '') {
            throw new CustomUserMessageAuthenticationException('Atlas session has no subject.');
        }

        return new SelfValidatingPassport(
            new UserBadge($subject, static fn (string $identifier): AtlasUser => new AtlasUser($identifier, $claims)),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Let the request proceed to the controller.
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['error' => 'unauthenticated', 'message' => $exception->getMessageKey()],
            Response::HTTP_UNAUTHORIZED,
        );
    }

    /**
     * The two legitimate carriers, header first: an `Authorization: Bearer <jwt>`
     * deliberately set by the caller wins over a possibly stale `__session` cookie.
     */
    private static function extractToken(Request $request): ?string
    {
        $authorization = $request->headers->get('Authorization');
        if (is_string($authorization) && str_starts_with($authorization, 'Bearer ')) {
            $bearer = substr($authorization, 7);
            if ($bearer !== '') {
                return $bearer;
            }
        }

        $cookie = $request->cookies->get('__session');

        return is_string($cookie) && $cookie !== '' ? $cookie : null;
    }
}
