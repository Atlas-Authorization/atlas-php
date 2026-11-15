<?php

declare(strict_types=1);

namespace Atlas\Psr15;

use Atlas\Rp\RelyingParty;
use Atlas\Rp\RpException;
use Atlas\Rp\SealedCookie;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware that answers the hosted-redirect sign-in routes (Slim, Mezzio, Laminas, any PSR-15 pipeline) and passes every other
 * request on: `GET /auth/sign-in`, `GET /auth/callback`, `POST|GET /auth/sign-out` and, when you pass `onBackchannelLogout`,
 * `POST /auth/backchannel-logout`.
 *
 * Your session stays yours: `onSignedIn($claims, $tokens, $request)` returns the `Set-Cookie` header value(s) that start it;
 * `onSignOut($request)` ends it and returns `['sid' => ..., 'idToken' => ..., 'setCookies' => [...]]`.
 *
 *     new AtlasRelyingPartyMiddleware($rp, $responses,
 *         onSignedIn: fn (array $claims, array $tokens, $request) => [$sessions->create($claims['sub'], $claims['sid'] ?? null)],
 *         onSignOut: fn ($request) => ['sid' => $sessions->sid($request), 'setCookies' => [$sessions->clear()]]);
 */
final class AtlasRelyingPartyMiddleware implements MiddlewareInterface
{
    /** @var callable(array<string,mixed>,array<string,mixed>,ServerRequestInterface):(string|list<string>|null) */
    private $onSignedIn;
    /** @var (callable(ServerRequestInterface):array<string,mixed>)|null */
    private $onSignOut;
    /** @var (callable(list<string>,\Atlas\Verify\LogoutTokenClaims):void)|null */
    private $onBackchannelLogout;
    /** @var array{signIn:string,callback:string,signOut:string,backchannelLogout:string} */
    private array $routes;

    /**
     * @param callable                          $onSignedIn          starts your session; returns its `Set-Cookie` value(s)
     * @param (callable)|null                   $onSignOut           ends your session
     * @param (callable)|null                   $onBackchannelLogout `(list<string> $appSessionIds, LogoutTokenClaims $claims)`
     * @param array<string,string>              $routes              override `signIn`, `callback`, `signOut`, `backchannelLogout`
     * @param bool                              $secureCookies       set false only for plain-http local development
     */
    public function __construct(
        private readonly RelyingParty $rp,
        private readonly ResponseFactoryInterface $responses,
        callable $onSignedIn,
        ?callable $onSignOut = null,
        ?callable $onBackchannelLogout = null,
        array $routes = [],
        private readonly bool $secureCookies = true,
    ) {
        $this->onSignedIn = $onSignedIn;
        $this->onSignOut = $onSignOut;
        $this->onBackchannelLogout = $onBackchannelLogout;
        $this->routes = $routes + ['signIn' => '/auth/sign-in', 'callback' => '/auth/callback', 'signOut' => '/auth/sign-out', 'backchannelLogout' => '/auth/backchannel-logout'];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = rtrim($request->getUri()->getPath(), '/') ?: '/';
        $method = strtoupper($request->getMethod());
        try {
            return match (true) {
                $path === $this->routes['signIn'] && $method === 'GET' => $this->signIn($request),
                $path === $this->routes['callback'] && $method === 'GET' => $this->callback($request),
                $path === $this->routes['signOut'] && ($method === 'POST' || $method === 'GET') => $this->signOut($request),
                $path === $this->routes['backchannelLogout'] && $method === 'POST' && $this->onBackchannelLogout !== null => $this->backchannel($request),
                default => $handler->handle($request),
            };
        } catch (RpException $e) {
            // A failed callback always clears the sign-in cookie.
            return $this->text(400, $e->getMessage())->withAddedHeader('Set-Cookie', SealedCookie::clear(RelyingParty::STATE_COOKIE, $this->secureCookies));
        }
    }

    private function signIn(ServerRequestInterface $request): ResponseInterface
    {
        $start = $this->rp->start($request->getQueryParams());

        return $this->responses->createResponse(302)->withHeader('Location', $start['url'])->withAddedHeader('Set-Cookie', $this->secure($start['set_cookie']))->withHeader('Cache-Control', 'no-store');
    }

    private function callback(ServerRequestInterface $request): ResponseInterface
    {
        $done = $this->rp->callback($request->getQueryParams(), $request->getCookieParams()[RelyingParty::STATE_COOKIE] ?? null);
        $response = $this->responses->createResponse(302)->withHeader('Location', $done['return_to'])->withHeader('Cache-Control', 'no-store');
        foreach ((array) ($this->onSignedIn)($done['claims'], $done['tokens'], $request) as $cookie) {
            $response = $response->withAddedHeader('Set-Cookie', (string) $cookie);
        }

        return $response->withAddedHeader('Set-Cookie', SealedCookie::clear(RelyingParty::STATE_COOKIE, $this->secureCookies));
    }

    private function signOut(ServerRequestInterface $request): ResponseInterface
    {
        $ended = $this->onSignOut === null ? [] : ($this->onSignOut)($request);
        $response = $this->responses->createResponse(302)
            ->withHeader('Location', $this->rp->signOutUrl($ended['idToken'] ?? null))
            ->withHeader('Cache-Control', 'no-store');
        foreach ((array) ($ended['setCookies'] ?? []) as $cookie) {
            $response = $response->withAddedHeader('Set-Cookie', (string) $cookie);
        }

        return $response;
    }

    private function backchannel(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $token = is_array($body) && is_string($body['logout_token'] ?? null) ? $body['logout_token'] : '';
        [$status, $headers] = $this->rp->backchannelLogout($token, $this->onBackchannelLogout);
        $response = $this->responses->createResponse($status);
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    private function secure(string $header): string
    {
        return $this->secureCookies ? $header : str_replace('; Secure', '', $header);
    }

    private function text(int $status, string $message): ResponseInterface
    {
        $response = $this->responses->createResponse($status)->withHeader('Content-Type', 'text/plain; charset=utf-8')->withHeader('Cache-Control', 'no-store');
        $response->getBody()->write($message);

        return $response;
    }
}
