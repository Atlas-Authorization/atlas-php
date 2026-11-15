<?php

declare(strict_types=1);

namespace Atlas\Tests\Psr15;

use Atlas\Psr15\AtlasRelyingPartyMiddleware;
use Atlas\Rp\RelyingParty;
use Atlas\Rp\SealedCookie;
use Atlas\Tests\Support\MockClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AtlasRelyingPartyMiddlewareTest extends TestCase
{
    private function mw(MockClient $mock, ?callable $onSignedIn = null): AtlasRelyingPartyMiddleware
    {
        $f = new HttpFactory();
        $rp = new RelyingParty('https://atlasauth.net', 'client_1', 'https://app.example.com/auth/callback', 'a-session-secret-of-at-least-32-chars', 'shh', httpClient: $mock, requestFactory: $f, streamFactory: $f);

        return new AtlasRelyingPartyMiddleware($rp, $f, $onSignedIn ?? static fn (): array => ['sess=1; Path=/'], static fn (): array => ['sid' => 's', 'setCookies' => ['sess=; Max-Age=0']]);
    }

    private function next(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'app');
            }
        };
    }

    public function testSignInRedirectsToAtlasAndSetsTheStateCookie(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['issuer' => 'https://atlasauth.net', 'authorization_endpoint' => 'https://atlasauth.net/oauth2/authorize', 'token_endpoint' => 'https://atlasauth.net/oauth2/token', 'jwks_uri' => 'https://atlasauth.net/oauth2/jwks']);
        $response = $this->mw($mock)->process((new ServerRequest('GET', '/auth/sign-in?return_to=/x'))->withQueryParams(['return_to' => '/x']), $this->next());
        self::assertSame(302, $response->getStatusCode());
        self::assertStringStartsWith('https://atlasauth.net/oauth2/authorize?', $response->getHeaderLine('Location'));
        self::assertStringContainsString(RelyingParty::STATE_COOKIE . '=', $response->getHeaderLine('Set-Cookie'));
    }

    public function testOtherPathsPassThrough(): void
    {
        $response = $this->mw(new MockClient())->process(new ServerRequest('GET', '/dashboard'), $this->next());
        self::assertSame('app', (string) $response->getBody());
    }

    public function testACallbackWithoutTheStateCookieIs400AndClearsIt(): void
    {
        $response = $this->mw(new MockClient())->process((new ServerRequest('GET', '/auth/callback'))->withQueryParams(['code' => 'c', 'state' => 's']), $this->next());
        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('Max-Age=0', $response->getHeaderLine('Set-Cookie'));
    }

    public function testSignOutEndsYourSessionAndRedirects(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['issuer' => 'https://atlasauth.net', 'authorization_endpoint' => 'https://atlasauth.net/oauth2/authorize', 'token_endpoint' => 'https://atlasauth.net/oauth2/token', 'jwks_uri' => 'https://atlasauth.net/oauth2/jwks', 'end_session_endpoint' => 'https://atlasauth.net/oauth2/logout']);
        $response = $this->mw($mock)->process(new ServerRequest('POST', '/auth/sign-out'), $this->next());
        self::assertSame(302, $response->getStatusCode());
        self::assertStringStartsWith('https://atlasauth.net/oauth2/logout', $response->getHeaderLine('Location'));
        self::assertSame('sess=; Max-Age=0', $response->getHeaderLine('Set-Cookie'));
        self::assertInstanceOf(SealedCookie::class, new SealedCookie('a-session-secret-of-at-least-32-chars'));
    }
}
