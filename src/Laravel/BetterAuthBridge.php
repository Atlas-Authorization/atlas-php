<?php

declare(strict_types=1);

namespace Atlas\Laravel;

use Atlas\BetterAuth\SessionBridge;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Better Auth session bridge for Laravel (route alias `atlas.better_auth_bridge`, or add it as global
 * middleware ahead of `atlas.auth`). Same behaviour as the PSR-15 middleware: on a GET / HEAD with a
 * Better Auth session cookie and no Atlas session it exchanges the session once and 303s back to the
 * same URL with the Atlas cookies set, or to `atlas.better_auth_bridge.sign_in_url` when a second
 * factor is owed; Better Auth cookies are cleared only on a final refusal. Other methods pass through.
 *
 * The cookie header is read raw (Laravel's cookie encryption would otherwise null the foreign
 * cookies) and the Atlas cookies are written raw too. The end user's IP is `$request->ip()`, so
 * TrustProxies applies.
 */
final class BetterAuthBridge
{
    public function __construct(private readonly SessionBridge $bridge, private readonly ?string $signInUrl = null)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $plan = $this->bridge->plan(
            $request->getMethod(),
            (string) $request->headers->get('Cookie', ''),
            $request->fullUrl(),
            $request->ip(),
            $request->userAgent(),
            $this->signInUrl,
        );

        if ($plan->redirects()) {
            $response = new \Illuminate\Http\RedirectResponse((string) $plan->redirectTo, 303);
            $response->headers->set('Cache-Control', 'no-store');
        } else {
            $response = $next($request);
        }
        foreach ($plan->setCookies as $header) {
            $response->headers->set('Set-Cookie', $header, false);
        }

        return $response;
    }
}
