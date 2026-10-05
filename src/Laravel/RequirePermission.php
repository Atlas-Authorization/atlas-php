<?php

declare(strict_types=1);

namespace Atlas\Laravel;

use Atlas\Verify\VerifyResult;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware (alias `atlas.permission`) that gates a route on the verified
 * session's org role / permissions. Run it after `atlas.auth`, which attaches the
 * {@see VerifyResult} it reads.
 *
 * Arguments are the required conditions, e.g.
 *   `->middleware(['atlas.auth', 'atlas.permission:billing:read,members:read'])`
 * requires BOTH permissions, while a `role:` prefix requires an org role:
 *   `'atlas.permission:role:admin'`.
 *
 * Decisions use the verifier's {@see VerifyResult::has()} — read straight off the
 * token, never a network call — so a permission change takes effect within one
 * token lifetime. A failing check is a 403.
 */
final class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$conditions): Response
    {
        $result = $request->attributes->get('atlas');
        if (!$result instanceof VerifyResult || !$result->ok) {
            // atlas.auth did not run, or did not pass. Treat an un-gated route as denied.
            return $this->forbidden();
        }

        foreach ($conditions as $condition) {
            if (!$result->has(self::parse($condition))) {
                return $this->forbidden();
            }
        }

        return $next($request);
    }

    /**
     * @return array<string,mixed>
     */
    private static function parse(string $condition): array
    {
        if (str_starts_with($condition, 'role:')) {
            return ['role' => substr($condition, 5)];
        }

        return ['permission' => $condition];
    }

    private function forbidden(): JsonResponse
    {
        return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
    }
}
