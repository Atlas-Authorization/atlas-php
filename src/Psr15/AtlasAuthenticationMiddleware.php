<?php

declare(strict_types=1);

namespace Atlas\Psr15;

use Atlas\Exception\ConfigException;
use Atlas\Verify\SessionVerifier;
use Atlas\Verify\VerifyResult;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Framework-agnostic PSR-15 middleware that gates a request on a verified Atlas
 * session. Drop it into any PSR-15 pipeline — Slim, Mezzio, Laminas, a custom
 * dispatcher — ahead of the routes it should protect.
 *
 * It reuses the framework-free {@see SessionVerifier} (the same one the Laravel
 * middleware and the Symfony authenticator wrap), so there is no second copy of
 * the JWT/JWKS crypto here: this file only does the HTTP-plumbing — read the
 * token off the PSR-7 request, hand it to the verifier, branch on the result.
 *
 * Verification is LOCAL. The session JWT is checked against the instance's
 * cached JWKS with no network call on the hot path (§7.3), so a warm process
 * authenticates every request in-process; the revocation window is bounded by
 * the 60-second token lifetime instead of by an outbound call per request.
 *
 * On success the verified {@see VerifyResult} is attached to the request as the
 * `atlas` attribute (configurable) and the handler runs; read it downstream with
 * `$request->getAttribute('atlas')`, which carries `->claims` and the
 * `has()` / `protect()` authorization helpers. On a missing or invalid token the
 * handler never runs — the middleware short-circuits with a `401` JSON response.
 */
final class AtlasAuthenticationMiddleware implements MiddlewareInterface
{
    private readonly ResponseFactoryInterface $responseFactory;
    private readonly ?StreamFactoryInterface $streamFactory;

    /**
     * @param SessionVerifier               $verifier        the shared local verifier (reuse the one you built for the rest of the SDK)
     * @param ResponseFactoryInterface|null $responseFactory PSR-17 factory for the 401 response; Guzzle is auto-discovered when null
     * @param StreamFactoryInterface|null   $streamFactory   PSR-17 factory for the 401 body; Guzzle is auto-discovered when null
     * @param string                        $attribute       the request attribute the verified result is stored under
     */
    public function __construct(
        private readonly SessionVerifier $verifier,
        ?ResponseFactoryInterface $responseFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private readonly string $attribute = 'atlas',
    ) {
        if ($responseFactory === null || $streamFactory === null) {
            $factory = self::guzzleFactory();
            $responseFactory ??= $factory;
            $streamFactory ??= $factory;
        }
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // The verifier reads an `Authorization: Bearer <jwt>` header or, failing
        // that, the `__session` cookie off the PSR-7 message itself (header
        // first) — the same two carriers every other Atlas integration honours.
        $result = $this->verifier->authenticateRequest($request);
        if (!$result->ok) {
            return $this->unauthorized($result);
        }

        // Attach the verified result and let the rest of the pipeline run.
        return $handler->handle($request->withAttribute($this->attribute, $result));
    }

    private function unauthorized(VerifyResult $result): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(401)
            ->withHeader('Content-Type', 'application/json');

        $body = json_encode(
            ['error' => 'unauthenticated', 'reason' => $result->reason],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return $response->withBody($this->streamFactory->createStream($body));
    }

    private static function guzzleFactory(): ResponseFactoryInterface&StreamFactoryInterface
    {
        if (!class_exists(\GuzzleHttp\Psr7\HttpFactory::class)) {
            throw new ConfigException(
                'No PSR-17 factories were provided to AtlasAuthenticationMiddleware and guzzlehttp/psr7 is not '
                . 'installed. Install it or pass your own response/stream factories.',
            );
        }

        return new \GuzzleHttp\Psr7\HttpFactory();
    }
}
