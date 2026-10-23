<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\BetterAuth\SessionBridge;
use Atlas\Client;
use Atlas\Laravel\BetterAuthBridge;
use Atlas\Symfony\BetterAuthBridgeSubscriber;
use Atlas\Tests\Support\MockClient;
use Illuminate\Http\Request as LaravelRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as SfRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/** The Laravel middleware and the Symfony subscriber share SessionBridge::plan() with the PSR-15 middleware. */
final class BridgeFrameworksTest extends TestCase
{
    private const COOKIE = 'better-auth.session_token=tok; theme=dark';

    private static function bridge(MockClient $mock): SessionBridge
    {
        return new SessionBridge(new Client('sk_test_123', 'https://api.example.test', $mock));
    }

    private static function session(MockClient $mock): void
    {
        $mock->push(200, ['object' => 'session_tokens', 'jwt' => 'jwt9', 'refresh_token' => 'rt9']);
    }

    private static function refusal(MockClient $mock, string $outcome): void
    {
        $mock->push(400, ['errors' => [['code' => 'VERIFICATION_FAILED', 'message' => 'x', 'meta' => ['bridge_outcome' => $outcome]]]]);
    }

    /** @return list<string> */
    private static function setCookies(Response $r): array
    {
        return array_map('strval', $r->headers->all('set-cookie'));
    }

    private function laravel(MockClient $mock, ?string $signIn = '/sign-in'): BetterAuthBridge
    {
        return new BetterAuthBridge(self::bridge($mock), $signIn);
    }

    public function testLaravelSignedInRedirectsWithAtlasCookiesAndRealIp(): void
    {
        $mock = new MockClient();
        self::session($mock);
        $request = LaravelRequest::create('http://app.test/dash?x=1', 'GET', server: ['HTTP_COOKIE' => self::COOKIE, 'REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'UA/1']);
        $ran = false;

        $response = $this->laravel($mock)->handle($request, function () use (&$ran) { $ran = true; return new Response('x'); });

        $this->assertFalse($ran);
        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('http://app.test/dash?x=1', $response->headers->get('Location'));
        $cookies = implode("\n", self::setCookies($response));
        $this->assertStringContainsString('__session=jwt9', $cookies);
        $this->assertStringContainsString('__atlas_rt=rt9', $cookies);
        $this->assertStringContainsString('better-auth.session_token=', $cookies);
        $sent = $mock->requests[0] ?? null;
        $this->assertNotNull($sent);
        $this->assertSame('203.0.113.7', $sent->getHeaderLine('X-Atlas-Client-IP'));
        $this->assertSame('UA/1', $sent->getHeaderLine('X-Atlas-Client-User-Agent'));
    }

    public function testLaravelMfaRedirectsToSignInAndNonGetPassesThrough(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['object' => 'sign_in_attempt', 'id' => 'sia_7', 'status' => 'needs_second_factor']);
        $get = LaravelRequest::create('/dash', 'GET', server: ['HTTP_COOKIE' => self::COOKIE]);
        $response = $this->laravel($mock)->handle($get, fn () => new Response('x'));
        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringStartsWith('/sign-in?__atlas_attempt=sia_7&__atlas_status=', (string) $response->headers->get('Location'));

        $post = LaravelRequest::create('/dash', 'POST', server: ['HTTP_COOKIE' => self::COOKIE]);
        $response = $this->laravel(new MockClient())->handle($post, fn () => new Response('posted'));
        $this->assertSame('posted', $response->getContent());
        $this->assertSame([], self::setCookies($response));
    }

    public function testLaravelClearsOnFinalRefusalOnlyAndContinues(): void
    {
        $mock = new MockClient();
        self::refusal($mock, 'invalid');
        $mock->push(503, 'down');
        $request = fn () => LaravelRequest::create('/dash', 'GET', server: ['HTTP_COOKIE' => self::COOKIE]);

        $final = $this->laravel($mock)->handle($request(), fn () => new Response('anon'));
        $this->assertSame('anon', $final->getContent());
        $this->assertNotSame([], self::setCookies($final));

        $outage = $this->laravel($mock)->handle($request(), fn () => new Response('anon'));
        $this->assertSame('anon', $outage->getContent());
        $this->assertSame([], self::setCookies($outage));
    }

    public function testLaravelNoBetterAuthCookieOrAtlasSessionDoesNothing(): void
    {
        $mock = new MockClient();
        foreach (['theme=dark', self::COOKIE . '; __session=abc'] as $cookie) {
            $response = $this->laravel($mock)->handle(LaravelRequest::create('/', 'GET', server: ['HTTP_COOKIE' => $cookie]), fn () => new Response('ok'));
            $this->assertSame('ok', $response->getContent());
        }
        $this->assertSame([], $mock->requests);
    }

    private function symfonyRequestEvent(SfRequest $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createMock(HttpKernelInterface::class), $request, $type);
    }

    public function testSymfonySignedInRedirectsAndHonoursClientIp(): void
    {
        $mock = new MockClient();
        self::session($mock);
        $subscriber = new BetterAuthBridgeSubscriber(self::bridge($mock), '/sign-in');
        $request = SfRequest::create('http://app.test/dash', 'GET', server: ['HTTP_COOKIE' => self::COOKIE, 'REMOTE_ADDR' => '198.51.100.4']);
        $event = $this->symfonyRequestEvent($request);

        $subscriber->onRequest($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('http://app.test/dash', $response->headers->get('Location'));
        $this->assertStringContainsString('__session=jwt9', implode("\n", self::setCookies($response)));
        $this->assertSame('198.51.100.4', $mock->requests[0]->getHeaderLine('X-Atlas-Client-IP'));
    }

    public function testSymfonyRefusalClearsOnResponseAndSubRequestsAndPostAreIgnored(): void
    {
        $mock = new MockClient();
        self::refusal($mock, 'invalid');
        $subscriber = new BetterAuthBridgeSubscriber(self::bridge($mock), '/sign-in');
        $kernel = $this->createMock(HttpKernelInterface::class);

        $sub = $this->symfonyRequestEvent(SfRequest::create('/', 'GET', server: ['HTTP_COOKIE' => self::COOKIE]), HttpKernelInterface::SUB_REQUEST);
        $subscriber->onRequest($sub);
        $post = $this->symfonyRequestEvent(SfRequest::create('/', 'POST', server: ['HTTP_COOKIE' => self::COOKIE]));
        $subscriber->onRequest($post);
        $this->assertNull($sub->getResponse());
        $this->assertNull($post->getResponse());
        $this->assertSame([], $mock->requests);

        $request = SfRequest::create('/', 'GET', server: ['HTTP_COOKIE' => self::COOKIE]);
        $event = $this->symfonyRequestEvent($request);
        $subscriber->onRequest($event);
        $this->assertNull($event->getResponse());
        $out = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response('anon'));
        $subscriber->onResponse($out);
        $this->assertStringContainsString('better-auth.session_token=', implode("\n", self::setCookies($out->getResponse())));
    }

    public function testSymfonyMfaGoesToSignInAndSubscribesBeforeTheFirewall(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['object' => 'sign_in_attempt', 'id' => 'sia_7', 'status' => 'needs_second_factor']);
        $subscriber = new BetterAuthBridgeSubscriber(self::bridge($mock), '/sign-in');
        $event = $this->symfonyRequestEvent(SfRequest::create('/dash', 'GET', server: ['HTTP_COOKIE' => self::COOKIE]));
        $subscriber->onRequest($event);
        $this->assertStringStartsWith('/sign-in?__atlas_attempt=sia_7', (string) $event->getResponse()?->headers->get('Location'));
        $this->assertGreaterThan(8, BetterAuthBridgeSubscriber::getSubscribedEvents()['kernel.request'][1]);
    }

    public function testSymfonyExtensionRegistersTheSubscriberOnlyWhenEnabled(): void
    {
        $off = new \Symfony\Component\DependencyInjection\ContainerBuilder();
        (new \Atlas\Symfony\DependencyInjection\AtlasExtension())->load([['secret_key' => 'sk_test_1']], $off);
        $this->assertFalse($off->hasDefinition(BetterAuthBridgeSubscriber::class));

        $on = new \Symfony\Component\DependencyInjection\ContainerBuilder();
        (new \Atlas\Symfony\DependencyInjection\AtlasExtension())->load([['secret_key' => 'sk_test_1', 'better_auth_bridge' => ['enabled' => true, 'sign_in_url' => '/in']]], $on);
        $this->assertTrue($on->getDefinition(BetterAuthBridgeSubscriber::class)->hasTag('kernel.event_subscriber'));
        $this->assertSame('/in', $on->getDefinition(BetterAuthBridgeSubscriber::class)->getArgument('$signInUrl'));
    }
}
