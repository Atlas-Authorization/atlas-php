<?php

declare(strict_types=1);

namespace Atlas\Laravel;

use Atlas\Verify\SessionVerifier;
use Atlas\Verify\VerifyResult;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware (alias `atlas.auth`) that gates a route on a verified Atlas
 * session.
 *
 * It reads an `Authorization: Bearer <jwt>` header or, failing that, the
 * `__session` cookie — the same two carriers {@see SessionVerifier::authenticateRequest()}
 * honours, header first — verifies locally and attaches the result. A missing or
 * invalid token is a 401; the route never runs.
 *
 * On success the verified {@see VerifyResult} is available two ways:
 *   - `$request->attributes->get('atlas')` — the result, for downstream middleware
 *     such as {@see RequirePermission};
 *   - `$request->user()` — resolves to the same result, which carries `->claims`
 *     and the `has()` / `protect()` authorization helpers.
 */
final class AtlasAuthenticate
{
    public function __construct(private readonly SessionVerifier $verifier)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null || $token === '') {
            $cookie = $request->cookie('__session');
            $token = is_string($cookie) ? $cookie : null;
        }

        $result = $this->verifier->verify($token ?? '');
        if (!$result->ok) {
            return $this->unauthorized($result);
        }

        $request->attributes->set('atlas', $result);
        $request->setUserResolver(static fn (): VerifyResult => $result);

        return $next($request);
    }

    private function unauthorized(VerifyResult $result): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'unauthenticated', 'reason' => $result->reason],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
