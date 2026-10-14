<?php

declare(strict_types=1);

namespace Atlas\Tests\Psr15;

use Atlas\Psr15\AtlasAuthenticationMiddleware;
use Atlas\Tests\Support\VerifierHarness;
use Atlas\Verify\VerifyResult;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The framework-agnostic PSR-15 middleware: a valid token flows through to the
 * handler with the verified result attached; a missing/invalid one short-circuits
 * with a 401 and the handler never runs. The verifier underneath is the real
 * {@see \Atlas\Verify\SessionVerifier} primed off a mocked PSR-18 client — no
 * network.
 */
final class AtlasAuthenticationMiddlewareTest extends TestCase
{
    use VerifierHarness;

    private function middleware(): AtlasAuthenticationMiddleware
    {
        $factory = new HttpFactory();

        return new AtlasAuthenticationMiddleware($this->verifier(), $factory, $factory);
    }

    /**
     * A handler that records whether it ran and the request it was handed, then
     * returns a 200 — so a test can assert both that the pipeline proceeded and
     * what the middleware attached.
     */
    private function recordingHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public bool $called = false;
            public ?ServerRequestInterface $received = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;
                $this->received = $request;

                return new Response(200, [], 'ok');
            }
        };
    }

    public function testValidBearerTokenSetsAttributeAndCallsNext(): void
    {
        $handler = $this->recordingHandler();
        $request = new ServerRequest('GET', '/api/thing', ['Authorization' => 'Bearer ' . $this->mint(['sub' => 'user_42'])]);

        $response = $this->middleware()->process($request, $handler);

        $this->assertTrue($handler->called, 'the handler should run for a valid token');
        $this->assertSame(200, $response->getStatusCode());

        $attached = $handler->received?->getAttribute('atlas');
        $this->assertInstanceOf(VerifyResult::class, $attached);
        $this->assertTrue($attached->ok);
        $this->assertSame('user_42', $attached->claims['sub']);
    }

    public function testValidSessionCookieIsAccepted(): void
    {
        $handler = $this->recordingHandler();
        $request = (new ServerRequest('GET', '/api/thing'))
            ->withHeader('Cookie', '__session=' . $this->mint(['sub' => 'cookie_user']));

        $response = $this->middleware()->process($request, $handler);

        $this->assertTrue($handler->called);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('cookie_user', $handler->received?->getAttribute('atlas')->claims['sub']);
    }

    public function testInvalidTokenReturns401AndDoesNotCallNext(): void
    {
        $handler = $this->recordingHandler();
        // Wrong issuer → the verifier rejects it as `invalid`.
        $request = new ServerRequest('GET', '/api/thing', ['Authorization' => 'Bearer ' . $this->mint(['iss' => 'https://evil.test'])]);

        $response = $this->middleware()->process($request, $handler);

        $this->assertFalse($handler->called, 'the handler must not run for an invalid token');
        $this->assertSame(401, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('unauthenticated', $body['error']);
        $this->assertSame('invalid', $body['reason']);
    }

    public function testMissingTokenReturns401AndDoesNotCallNext(): void
    {
        $handler = $this->recordingHandler();
        $response = $this->middleware()->process(new ServerRequest('GET', '/api/thing'), $handler);

        $this->assertFalse($handler->called);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('malformed', json_decode((string) $response->getBody(), true)['reason']);
    }

    public function testAttributeNameIsConfigurable(): void
    {
        $handler = $this->recordingHandler();
        $factory = new HttpFactory();
        $middleware = new AtlasAuthenticationMiddleware($this->verifier(), $factory, $factory, 'session');
        $request = new ServerRequest('GET', '/api/thing', ['Authorization' => 'Bearer ' . $this->mint()]);

        $middleware->process($request, $handler);

        $this->assertInstanceOf(VerifyResult::class, $handler->received?->getAttribute('session'));
        $this->assertNull($handler->received?->getAttribute('atlas'));
    }
}
