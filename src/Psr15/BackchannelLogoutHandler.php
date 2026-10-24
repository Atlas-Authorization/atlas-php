<?php

declare(strict_types=1);

namespace Atlas\Psr15;

use Atlas\Verify\BackchannelLogout;
use Atlas\Verify\LogoutTokenClaims;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A PSR-15 handler for the `backchannel_logout_uri` route: it reads the form-posted `logout_token`, verifies it with
 * {@see BackchannelLogout} and calls your callback. It answers 200 after the callback ran, 400 for a token that must be refused,
 * and 503 + `Retry-After` when Atlas should retry (keys unreachable, or your callback threw).
 *
 *     $router->post('/atlas/logout', new BackchannelLogoutHandler($logout, $responses,
 *         fn (LogoutTokenClaims $c) => $sessions->endFor($c->sub, $c->sid)));
 */
final class BackchannelLogoutHandler implements RequestHandlerInterface
{
    /** @var callable(LogoutTokenClaims):void */
    private $onLogout;

    /**
     * @param callable(LogoutTokenClaims):void $onLogout
     */
    public function __construct(
        private readonly BackchannelLogout $verifier,
        private readonly ResponseFactoryInterface $responses,
        callable $onLogout,
    ) {
        $this->onLogout = $onLogout;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return $this->responses->createResponse(405)->withHeader('Allow', 'POST');
        }
        $body = $request->getParsedBody();
        $token = is_array($body) && is_string($body['logout_token'] ?? null) ? $body['logout_token'] : '';
        [$status, $headers] = $this->verifier->handle($token, $this->onLogout);
        $response = $this->responses->createResponse($status);
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
