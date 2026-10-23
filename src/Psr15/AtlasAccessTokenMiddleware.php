<?php

declare(strict_types=1);

namespace Atlas\Psr15;

use Atlas\Verify\AccessTokenVerifier;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware that protects an API with an Atlas-issued OAuth access token
 * (an `Authorization: Bearer` or `DPoP` header, never a cookie). A DPoP-bound
 * token must come with a valid `DPoP` proof for this request (RFC 9449). A
 * refusal short-circuits with the verifier's status and RFC 6750 / RFC 9449 /
 * RFC 9728 `WWW-Authenticate` challenge; on success the
 * {@see \Atlas\Verify\AccessTokenResult} is attached as the `atlas_access_token`
 * attribute (configurable). The verifier's `DPoP-Nonce`, when it issues nonces,
 * is set on every response.
 */
final class AtlasAccessTokenMiddleware implements MiddlewareInterface
{
    private readonly ResponseFactoryInterface $responseFactory;
    private readonly StreamFactoryInterface $streamFactory;

    /** @param list<string> $requiredScopes */
    public function __construct(
        private readonly AccessTokenVerifier $verifier,
        private readonly array $requiredScopes = [],
        ?ResponseFactoryInterface $responseFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private readonly string $attribute = 'atlas_access_token',
    ) {
        $factory = ($responseFactory === null || $streamFactory === null) ? new \GuzzleHttp\Psr7\HttpFactory() : null;
        $this->responseFactory = $responseFactory ?? $factory;
        $this->streamFactory = $streamFactory ?? $factory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $result = $this->verifier->verifyRequest($request, $this->requiredScopes);
        if (!$result->ok) {
            $body = json_encode(['error' => $result->error ?? 'unauthorized'], JSON_THROW_ON_ERROR);
            $response = $this->responseFactory->createResponse($result->status)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('WWW-Authenticate', $result->wwwAuthenticate)
                ->withBody($this->streamFactory->createStream($body));

            return $result->dpopNonce !== null ? $response->withHeader('DPoP-Nonce', $result->dpopNonce) : $response;
        }
        $response = $handler->handle($request->withAttribute($this->attribute, $result));

        return $result->dpopNonce !== null ? $response->withHeader('DPoP-Nonce', $result->dpopNonce) : $response;
    }
}
