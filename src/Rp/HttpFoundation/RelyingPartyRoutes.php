<?php

declare(strict_types=1);

namespace Atlas\Rp\HttpFoundation;

use Atlas\Rp\RelyingParty;
use Atlas\Rp\RpException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The four hosted-redirect sign-in actions over `symfony/http-foundation` (the request and response types of both Laravel and
 * Symfony). The Laravel controller and the Symfony controller are thin wrappers around this class.
 */
final class RelyingPartyRoutes
{
    public function __construct(
        private readonly RelyingParty $rp,
        private readonly RpSessionHandler $sessions,
        private readonly bool $secureCookies = true,
    ) {
    }

    public function signIn(Request $request): Response
    {
        $start = $this->rp->start($request->query->all());
        $response = new RedirectResponse($start['url']);
        $response->headers->setCookie($this->stateCookie($start['cookie'], 600));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    public function callback(Request $request): Response
    {
        try {
            $done = $this->rp->callback($request->query->all(), $request->cookies->get(RelyingParty::STATE_COOKIE));
        } catch (RpException $e) {
            $response = new Response($e->getMessage(), 400, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
            $response->headers->clearCookie(RelyingParty::STATE_COOKIE, '/', null, $this->secureCookies, true, 'lax');

            return $response;
        }
        $response = new RedirectResponse($done['return_to']);
        foreach ($this->sessions->signedIn($done['claims'], $done['tokens'], $request) as $cookie) {
            $response->headers->setCookie($cookie);
        }
        $response->headers->clearCookie(RelyingParty::STATE_COOKIE, '/', null, $this->secureCookies, true, 'lax');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    public function signOut(Request $request): Response
    {
        $ended = $this->sessions->signedOut($request);
        $response = new RedirectResponse($this->rp->signOutUrl($ended['idToken'] ?? null));
        foreach ($ended['cookies'] ?? [] as $cookie) {
            $response->headers->setCookie($cookie);
        }
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    public function backchannelLogout(Request $request): Response
    {
        [$status, $headers] = $this->rp->backchannelLogout((string) $request->request->get('logout_token', ''), $this->sessions->backchannelLogout(...));

        return new Response('', $status, $headers);
    }

    private function stateCookie(string $value, int $ttl): Cookie
    {
        return Cookie::create(RelyingParty::STATE_COOKIE, $value, time() + $ttl, '/', null, $this->secureCookies, true, true, 'lax');
    }
}
