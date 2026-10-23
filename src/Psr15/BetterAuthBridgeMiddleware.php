<?php

declare(strict_types=1);

namespace Atlas\Psr15;

use Atlas\BetterAuth\BridgeDecision;
use Atlas\BetterAuth\SessionBridge;
use Atlas\Exception\ConfigException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 Better Auth session bridge: put it ahead of your auth middleware during a migration from
 * Better Auth. On a GET or HEAD that carries a Better Auth session cookie (and no Atlas session) it
 * exchanges the session once with Atlas and:
 *
 * - signed in: redirects (303) to the same URL with `__session` / `__atlas_rt` set and the Better Auth
 *   cookies cleared, so the next request is an ordinary Atlas request;
 * - a second factor is owed: redirects to `$signInUrl` with `__atlas_attempt` / `__atlas_status`
 *   (the Better Auth cookies cleared; the session is spent);
 * - refused: runs the handler unauthenticated, clearing the Better Auth cookies only when the refusal
 *   is final (a closed bridge, a 429 or an outage keeps them, to retry later).
 *
 * Any other method passes straight through. {@see AtlasAuthenticationMiddleware} takes the same bridge
 * as its `$betterAuthBridge` option.
 */
final class BetterAuthBridgeMiddleware implements MiddlewareInterface
{
    private readonly ResponseFactoryInterface $responseFactory;

    /** @var \Closure(ServerRequestInterface):?string */
    private readonly \Closure $clientIp;

    /**
     * @param SessionBridge                                  $bridge          the bridge (an Atlas client on the secret key)
     * @param string|null                                    $signInUrl       where a user who still owes a second factor goes;
     *                                                                        null continues unauthenticated instead
     * @param ResponseFactoryInterface|null                  $responseFactory PSR-17 factory for the redirects; Guzzle when null
     * @param callable(ServerRequestInterface):?string|null  $clientIp        resolve the END USER's IP; default: the `ip_address`
     *                                                                        attribute, else the first `X-Forwarded-For` hop, else
     *                                                                        `REMOTE_ADDR` (only as trustworthy as your proxy)
     */
    public function __construct(
        private readonly SessionBridge $bridge,
        private readonly ?string $signInUrl = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?callable $clientIp = null,
    ) {
        if ($responseFactory === null) {
            if (!class_exists(\GuzzleHttp\Psr7\HttpFactory::class)) {
                throw new ConfigException('BetterAuthBridgeMiddleware needs a PSR-17 response factory (or guzzlehttp/psr7).');
            }
            $responseFactory = new \GuzzleHttp\Psr7\HttpFactory();
        }
        $this->responseFactory = $responseFactory;
        $this->clientIp = $clientIp !== null ? \Closure::fromCallable($clientIp) : self::defaultClientIp(...);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $redirect = $this->bridge($request);
        if ($redirect !== null) {
            return $redirect;
        }

        return self::finish($request, $handler->handle($request));
    }

    /**
     * Run the bridge for a request. Returns the redirect to send, or null to continue; then `$request`
     * may carry cookie clears that {@see finish()} appends to the handler's response.
     *
     * @internal shared with {@see AtlasAuthenticationMiddleware}
     */
    public function bridge(ServerRequestInterface &$request): ?ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        if ($method !== 'GET' && $method !== 'HEAD') {
            return null;
        }
        $cookies = $request->getCookieParams();
        if ($cookies === []) {
            $cookies = $request->getHeaderLine('Cookie');
        }
        $userAgent = $request->getHeaderLine('User-Agent');
        $decision = $this->bridge->decide($cookies, ($this->clientIp)($request), $userAgent !== '' ? $userAgent : null);

        if ($decision->action === BridgeDecision::SIGNED_IN) {
            return $this->redirect((string) $request->getUri(), $decision);
        }
        if ($decision->action === BridgeDecision::NEEDS_MFA && $this->signInUrl !== null && $this->signInUrl !== '') {
            return $this->redirect($decision->continuationUrl($this->signInUrl), $decision);
        }
        if ($decision->cookies !== []) {
            // Continue, but the response must clear the dead Better Auth cookies.
            $request = $request->withAttribute(self::CLEAR_ATTRIBUTE, $decision->setCookieHeaders());
        }

        return null;
    }

    /** Request attribute carrying the `Set-Cookie` values to append on a pass-through. */
    public const CLEAR_ATTRIBUTE = 'atlas.better_auth_clear_cookies';

    /** Append the pass-through cookie clears the bridge recorded on the request. */
    public static function finish(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $headers = $request->getAttribute(self::CLEAR_ATTRIBUTE);
        if (is_array($headers)) {
            foreach ($headers as $header) {
                $response = $response->withAddedHeader('Set-Cookie', (string) $header);
            }
        }

        return $response;
    }

    private function redirect(string $location, BridgeDecision $decision): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(303)
            ->withHeader('Location', $location)
            ->withHeader('Cache-Control', 'no-store');
        foreach ($decision->setCookieHeaders() as $header) {
            $response = $response->withAddedHeader('Set-Cookie', $header);
        }

        return $response;
    }

    private static function defaultClientIp(ServerRequestInterface $request): ?string
    {
        $attr = $request->getAttribute('ip_address');
        if (is_string($attr) && $attr !== '') {
            return $attr;
        }
        $xff = trim(explode(',', $request->getHeaderLine('X-Forwarded-For'))[0]);
        if ($xff !== '') {
            return $xff;
        }
        $remote = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($remote) && $remote !== '' ? $remote : null;
    }
}
