<?php

declare(strict_types=1);

namespace Atlas\Laravel;

use Atlas\Rp\HttpFoundation\RelyingPartyRoutes;
use Atlas\Rp\HttpFoundation\RpSessionHandler;
use Atlas\Rp\RelyingParty;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel routes for the hosted-redirect sign-in. Bind your {@see RpSessionHandler} in the container (it starts and ends YOUR session),
 * set the `atlas.rp` config (or the ATLAS_RP_* env vars), then register the routes:
 *
 *     AtlasRelyingPartyController::routes($router);   // GET /auth/sign-in, GET /auth/callback, POST /auth/sign-out, POST /auth/backchannel-logout
 *
 * The back-channel route is POST and carries no CSRF token: exclude it from `VerifyCsrfToken`.
 */
final class AtlasRelyingPartyController
{
    public function __construct(private readonly RelyingPartyRoutes $routes)
    {
    }

    public static function routes(Router $router, string $prefix = 'auth'): void
    {
        $router->get("$prefix/sign-in", [self::class, 'signIn'])->name('atlas.rp.sign-in');
        $router->get("$prefix/callback", [self::class, 'callback'])->name('atlas.rp.callback');
        $router->match(['GET', 'POST'], "$prefix/sign-out", [self::class, 'signOut'])->name('atlas.rp.sign-out');
        $router->post("$prefix/backchannel-logout", [self::class, 'backchannelLogout'])->name('atlas.rp.backchannel-logout');
    }

    public function signIn(Request $request): Response
    {
        return $this->routes->signIn($request);
    }

    public function callback(Request $request): Response
    {
        return $this->routes->callback($request);
    }

    public function signOut(Request $request): Response
    {
        return $this->routes->signOut($request);
    }

    public function backchannelLogout(Request $request): Response
    {
        return $this->routes->backchannelLogout($request);
    }

    /** Build the relying party from `config('atlas.rp')`. @param array<string,mixed> $config */
    public static function relyingParty(array $config): RelyingParty
    {
        return new RelyingParty(
            issuer: (string) ($config['issuer'] ?? ''),
            clientId: (string) ($config['client_id'] ?? ''),
            redirectUri: (string) ($config['redirect_uri'] ?? ''),
            secret: (string) ($config['secret'] ?? ''),
            clientSecret: $config['client_secret'] ?? null,
            secretKey: $config['secret_key'] ?? null,
            postLogoutRedirectUri: $config['post_logout_redirect_uri'] ?? null,
            forwardParams: array_values((array) ($config['forward_params'] ?? [])),
        );
    }
}
