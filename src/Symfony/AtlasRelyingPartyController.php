<?php

declare(strict_types=1);

namespace Atlas\Symfony;

use Atlas\Rp\HttpFoundation\RelyingPartyRoutes;
use Atlas\Rp\HttpFoundation\RpSessionHandler;
use Atlas\Rp\RelyingParty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Symfony controller for the hosted-redirect sign-in. Register it as a service (autowire a {@see RelyingParty} and your
 * {@see RpSessionHandler}, which starts and ends YOUR session) and route to its four actions:
 *
 *     # config/routes.yaml
 *     atlas_sign_in:  { path: /auth/sign-in,  controller: Atlas\Symfony\AtlasRelyingPartyController::signIn,  methods: [GET] }
 *     atlas_callback: { path: /auth/callback, controller: Atlas\Symfony\AtlasRelyingPartyController::callback, methods: [GET] }
 *     atlas_sign_out: { path: /auth/sign-out, controller: Atlas\Symfony\AtlasRelyingPartyController::signOut, methods: [GET, POST] }
 *     atlas_backchannel: { path: /auth/backchannel-logout, controller: Atlas\Symfony\AtlasRelyingPartyController::backchannelLogout, methods: [POST] }
 */
final class AtlasRelyingPartyController
{
    private readonly RelyingPartyRoutes $routes;

    public function __construct(RelyingParty $rp, RpSessionHandler $sessions, bool $secureCookies = true)
    {
        $this->routes = new RelyingPartyRoutes($rp, $sessions, $secureCookies);
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
}
