<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\BetterAuth\BetterAuth;
use Atlas\BetterAuth\BridgeCookie;
use Atlas\BetterAuth\BridgeDecision;
use Atlas\BetterAuth\ExchangeResult;
use Atlas\BetterAuth\SessionBridge;
use Atlas\Client;
use Atlas\Exception\ConfigException;
use Atlas\Http;
use Atlas\Psr15\AtlasAuthenticationMiddleware;
use Atlas\Psr15\BetterAuthBridgeMiddleware;
use Atlas\Resources\BetterAuthImports;
use Atlas\Tests\Support\MockClient;
use Atlas\Tests\Support\VerifierHarness;
use Atlas\Verify\LegacyIssuer;
use Atlas\Verify\LegacyIssuerVerifier;
use Atlas\Verify\SessionVerifier;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Migrate from Better Auth: sealed-bundle imports (routes, line-aligned upload, wait), the bridge
 * settings and the secret-key exchange (classified outcomes), the request-level bridge and its PSR-15
 * middleware, and Better Auth JWTs accepted by the session verifier during the cutover.
 */
final class BetterAuthTest extends TestCase
{
    use VerifierHarness;

    private const API = 'https://api.atlas.test';

    private static function client(MockClient $mock): Client
    {
        return new Client('sk_test_123', self::API, $mock);
    }

    // ---- the 16 operations ---------------------------------------------------------------

    /**
     * @return list<array{0:string,1:string,2:array<string,mixed>|null,3:\Closure(Client):mixed}>
     */
    private static function operations(): array
    {
        return [
            ['GET', '/v1/better_auth_imports/public_key', null, static fn (Client $c) => $c->betterAuthImports->getPublicKey()],
            ['POST', '/v1/better_auth_imports/public_key/rotate', null, static fn (Client $c) => $c->betterAuthImports->rotatePublicKey()],
            ['POST', '/v1/better_auth_imports', ['options' => ['on_email_conflict' => 'link']], static fn (Client $c) => $c->betterAuthImports->create(['options' => ['on_email_conflict' => 'link']])],
            ['POST', '/v1/better_auth_imports', [], static fn (Client $c) => $c->betterAuthImports->create()],
            ['GET', '/v1/better_auth_imports?limit=5&starting_after=bai_0', null, static fn (Client $c) => $c->betterAuthImports->list(5, 'bai_0')],
            ['GET', '/v1/better_auth_imports', null, static fn (Client $c) => $c->betterAuthImports->list()],
            ['GET', '/v1/better_auth_imports/bai_1', null, static fn (Client $c) => $c->betterAuthImports->get('bai_1')],
            ['POST', '/v1/better_auth_imports/bai_1/finalize', null, static fn (Client $c) => $c->betterAuthImports->finalize('bai_1')],
            ['POST', '/v1/better_auth_imports/bai_1/dry_run', ['options' => ['models' => ['user']]], static fn (Client $c) => $c->betterAuthImports->dryRun('bai_1', ['options' => ['models' => ['user']]])],
            ['POST', '/v1/better_auth_imports/bai_1/apply', ['confirm_conflicts' => true], static fn (Client $c) => $c->betterAuthImports->apply('bai_1', ['confirm_conflicts' => true])],
            ['POST', '/v1/better_auth_imports/bai_1/apply', [], static fn (Client $c) => $c->betterAuthImports->apply('bai_1')],
            ['DELETE', '/v1/better_auth_imports/bai_1', null, static fn (Client $c) => $c->betterAuthImports->delete('bai_1')],
            ['GET', '/v1/better_auth_imports/jwks', null, static fn (Client $c) => $c->betterAuthImports->listJwks()],
            ['DELETE', '/v1/better_auth_imports/jwks/k%2F1', null, static fn (Client $c) => $c->betterAuthImports->deleteJwk('k/1')],
            ['GET', '/v1/migrations/better_auth/bridge', null, static fn (Client $c) => $c->betterAuthBridge->get()],
            ['PUT', '/v1/migrations/better_auth/bridge', ['enabled' => true, 'live_fallback' => ['secret' => null]], static fn (Client $c) => $c->betterAuthBridge->update(['enabled' => true, 'live_fallback' => ['secret' => null]])],
            ['DELETE', '/v1/migrations/better_auth/bridge', null, static fn (Client $c) => $c->betterAuthBridge->delete()],
            ['POST', '/v1/migrations/better_auth/exchange', ['token' => 'tok.sig'], static fn (Client $c) => $c->betterAuthBridge->exchange('tok.sig')],
            ['POST', '/v1/migrations/better_auth/exchange', ['token' => 't', 'remember' => false], static fn (Client $c) => $c->betterAuthBridge->exchange('t', remember: false)],
        ];
    }

    public function testEveryOperationHitsItsRoute(): void
    {
        foreach (self::operations() as [$verb, $path, $body, $call]) {
            $mock = new MockClient();
            $mock->push(200, ['ok' => true]);
            $call(self::client($mock));
            $request = $mock->lastRequest();
            $this->assertSame($verb, $request->getMethod(), $path);
            $this->assertSame(self::API . $path, (string) $request->getUri());
            $this->assertSame('Bearer sk_test_123', $request->getHeaderLine('Authorization'));
            $sent = (string) $request->getBody();
            if ($body === null) {
                $this->assertSame('', $sent, "{$verb} {$path} should send no body");
            } elseif ($body === []) {
                $this->assertSame('{}', $sent, "{$verb} {$path} body");
            } else {
                $this->assertSame($body, json_decode($sent, true), "{$verb} {$path} body");
            }
        }
    }

    public function testUploadPartIsTextPlainAndIdempotencyKeys(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['object' => 'better_auth_import_part', 'part' => 2]);
        $mock->push(200, ['kid' => 'k2']);
        $mock->push(201, ['id' => 'bai_1']);
        $mock->push(200, ['deleted' => true]);
        $c = self::client($mock);
        $c->betterAuthImports->uploadPart('bai_1', 2, "line-a\nline-b\n");
        $req = $mock->lastRequest();
        $this->assertSame('PUT', $req->getMethod());
        $this->assertSame(self::API . '/v1/better_auth_imports/bai_1/parts/2', (string) $req->getUri());
        $this->assertSame('text/plain; charset=utf-8', $req->getHeaderLine('Content-Type'));
        $this->assertSame("line-a\nline-b\n", (string) $req->getBody());
        $c->betterAuthImports->rotatePublicKey('idem-1');
        $this->assertSame('idem-1', $mock->lastRequest()->getHeaderLine('Idempotency-Key'));
        $c->betterAuthImports->create([], 'idem-2');
        $this->assertSame('idem-2', $mock->lastRequest()->getHeaderLine('Idempotency-Key'));
        $c->betterAuthBridge->delete('idem-3');
        $this->assertSame('idem-3', $mock->lastRequest()->getHeaderLine('Idempotency-Key'));
    }

    public function testExchangeForwardsTheEndUsersIpAndUserAgentButNeverTheAuthorization(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['object' => 'session_tokens']);
        $mock->push(200, ['object' => 'session_tokens']);
        $c = self::client($mock);
        $c->betterAuthBridge->exchange('tok', '203.0.113.9', 'Mozilla/5.0 test');
        $req = $mock->lastRequest();
        $this->assertSame('203.0.113.9', $req->getHeaderLine('X-Atlas-Client-IP'));
        $this->assertSame('Mozilla/5.0 test', $req->getHeaderLine('X-Atlas-Client-User-Agent'));
        $this->assertSame('Bearer sk_test_123', $req->getHeaderLine('Authorization'));
        $c->betterAuthBridge->exchange('tok');
        $this->assertFalse($mock->lastRequest()->hasHeader('X-Atlas-Client-IP'));
        $this->assertFalse($mock->lastRequest()->hasHeader('X-Atlas-Client-User-Agent'));

        // A caller-supplied header can never replace the Bearer secret.
        $mock->push(200, []);
        $c->http->request('GET', '/v1/x', headers: ['authorization' => 'Bearer evil', 'X-Extra' => 'y']);
        $this->assertSame('Bearer sk_test_123', $mock->lastRequest()->getHeaderLine('Authorization'));
        $this->assertSame('y', $mock->lastRequest()->getHeaderLine('X-Extra'));
    }

    // ---- bundle upload --------------------------------------------------------------------

    public function testSplitBundlePartsIsLineAligned(): void
    {
        $this->assertSame([], BetterAuthImports::splitBundleParts("\n\r\n"));
        $this->assertSame(["a\nb\n"], BetterAuthImports::splitBundleParts("a\r\nb"));
        $this->assertSame(["aaa\n", "bb\ncc\n", "dddddddd\n", "e\n"], BetterAuthImports::splitBundleParts("aaa\nbb\ncc\ndddddddd\ne\n", 6));
        foreach (BetterAuthImports::splitBundleParts(str_repeat("0123456789\n", 1000), 1000) as $part) {
            $this->assertLessThanOrEqual(1000, strlen($part));
            $this->assertStringEndsWith("\n", $part);
        }
        $this->assertSame(8 * 1024 * 1024, BetterAuthImports::PART_BYTES);
        $this->expectException(\InvalidArgumentException::class);
        BetterAuthImports::splitBundleParts('x', 0);
    }

    public function testUploadCreatesUploadsEveryPartAndFinalizes(): void
    {
        $mock = new MockClient();
        $mock->push(201, ['object' => 'better_auth_import', 'id' => 'bai_9', 'status' => 'uploading']);
        $mock->push(200, ['part' => 1]);
        $mock->push(200, ['part' => 2]);
        $mock->push(200, ['part' => 3]);
        $mock->push(200, ['id' => 'bai_9', 'status' => 'ready']);
        $seen = [];
        $result = self::client($mock)->betterAuthImports->upload(
            "h1\nr1\nr2x\nr3\n",
            ['import_sessions' => false],
            6,
            static function (int $part, int $total) use (&$seen): void {
                $seen[] = "{$part}/{$total}";
            },
        );
        $this->assertSame('ready', $result['status']);
        $this->assertSame(['1/3', '2/3', '3/3'], $seen);
        $r = $mock->requests;
        $this->assertCount(5, $r);
        $this->assertSame(['options' => ['import_sessions' => false]], json_decode((string) $r[0]->getBody(), true));
        $this->assertSame(self::API . '/v1/better_auth_imports/bai_9/parts/1', (string) $r[1]->getUri());
        $this->assertSame("h1\nr1\n", (string) $r[1]->getBody());
        $this->assertSame("r2x\n", (string) $r[2]->getBody());
        $this->assertSame("r3\n", (string) $r[3]->getBody());
        $this->assertSame(self::API . '/v1/better_auth_imports/bai_9/parts/3', (string) $r[3]->getUri());
        $this->assertSame(self::API . '/v1/better_auth_imports/bai_9/finalize', (string) $r[4]->getUri());

        $empty = new MockClient();
        try {
            self::client($empty)->betterAuthImports->upload("\n");
            $this->fail('expected an empty-bundle refusal');
        } catch (\InvalidArgumentException) {
            $this->assertSame(0, $empty->requestCount());
        }
    }

    public function testWaitForPollsUntilTheImportSettles(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['id' => 'bai_1', 'status' => 'analyzing']);
        $mock->push(200, ['id' => 'bai_1', 'status' => 'applying', 'progress' => ['percent' => 50]]);
        $mock->push(200, ['id' => 'bai_1', 'status' => 'completed']);
        $sleeps = [];
        $imports = new BetterAuthImports(
            new Http('sk_test_123', self::API, $mock, new HttpFactory(), new HttpFactory()),
            static function (float $s) use (&$sleeps): void {
                $sleeps[] = $s;
            },
        );
        $statuses = [];
        $done = $imports->waitFor('bai_1', 0.5, 60, static function (array $imp) use (&$statuses): void {
            $statuses[] = $imp['status'];
        });
        $this->assertSame('completed', $done['status']);
        $this->assertSame(['analyzing', 'applying', 'completed'], $statuses);
        $this->assertSame([0.5, 0.5], $sleeps);

        $stuck = new MockClient();
        for ($i = 0; $i < 4; $i++) {
            $stuck->push(200, ['status' => 'queued']);
        }
        $imports = new BetterAuthImports(new Http('sk_test_123', self::API, $stuck, new HttpFactory(), new HttpFactory()), static function (float $s): void {
        });
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Timed out');
        $imports->waitFor('bai_2', 1.0, 2.0);
    }

    // ---- classified exchange --------------------------------------------------------------

    public function testExchangeIsClassified(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['object' => 'session_tokens', 'jwt' => 'j', 'refresh_token' => 'r', 'session_id' => 'sess_1', 'user_id' => 'user_1', 'expires_in' => 60]);
        $mock->push(200, ['object' => 'sign_in_attempt', 'id' => 'sia_1', 'status' => 'needs_second_factor']);
        $mock->push(400, ['errors' => [['code' => 'VERIFICATION_FAILED', 'message' => 'x', 'meta' => ['bridge_outcome' => 'reused']]]]);
        $mock->push(403, ['errors' => [['code' => 'FORBIDDEN', 'message' => 'x', 'meta' => ['bridge_outcome' => 'closed']]]]);
        $mock->push(429, ['errors' => [['code' => 'RATE_LIMITED', 'message' => 'slow']]]);
        $mock->push(503, 'upstream');
        $mock->push(403, ['errors' => [['code' => 'IP_BLOCKED', 'message' => 'x']]]);
        $mock->pushException();
        $mock->push(200, ['object' => 'sign_in_attempt', 'id' => 'sia_2', 'status' => 'complete']);
        $c = self::client($mock);

        $s = BetterAuth::exchange($c, 'tok', '198.51.100.1', 'UA');
        $this->assertSame(ExchangeResult::SESSION, $s->kind);
        $this->assertSame(['j', 'r', 'sess_1', 'user_1', 60], [$s->jwt, $s->refreshToken, $s->sessionId, $s->userId, $s->expiresIn]);
        $m = BetterAuth::exchange($c, 'tok');
        $this->assertSame([ExchangeResult::NEEDS_MFA, 'sia_1', 'needs_second_factor'], [$m->kind, $m->attemptId, $m->status]);
        $r = BetterAuth::exchange($c, 'tok');
        $this->assertSame([ExchangeResult::REFUSED, 'reused', 400, true], [$r->kind, $r->outcome, $r->httpStatus, $r->isFinalRefusal()]);
        $r = BetterAuth::exchange($c, 'tok');
        $this->assertSame(['closed', 403, false], [$r->outcome, $r->httpStatus, $r->isFinalRefusal()]);
        $r = BetterAuth::exchange($c, 'tok');
        $this->assertSame(['rate_limited', 429, false], [$r->outcome, $r->httpStatus, $r->isFinalRefusal()]);
        $r = BetterAuth::exchange($c, 'tok');
        $this->assertSame(['unavailable', 503], [$r->outcome, $r->httpStatus]);
        $r = BetterAuth::exchange($c, 'tok');
        $this->assertSame(['unavailable', 403], [$r->outcome, $r->httpStatus]);
        $r = BetterAuth::exchange($c, 'tok');
        $this->assertSame(['unavailable', 0], [$r->outcome, $r->httpStatus]);
        $r = BetterAuth::exchange($c, 'tok');
        $this->assertSame([ExchangeResult::REFUSED, 'unavailable', 200], [$r->kind, $r->outcome, $r->httpStatus]);
    }

    // ---- the request-level bridge ---------------------------------------------------------

    public function testCookieNamesAndReading(): void
    {
        $names = BetterAuth::cookieNames();
        $this->assertSame(['__Secure-better-auth.session_token', 'better-auth.session_token'], $names['session_token']);
        $this->assertCount(8, $names['all']);
        $this->assertContains('__Secure-better-auth.dont_remember', $names['all']);
        $this->assertSame('sec', BetterAuth::readSessionCookie('better-auth.session_token=plain; __Secure-better-auth.session_token=sec'));
        $this->assertSame('plain', BetterAuth::readSessionCookie('a=1; better-auth.session_token=plain'));
        $this->assertSame('v', BetterAuth::readSessionCookie(['myapp.session_token' => 'v'], 'myapp'));
        $this->assertSame('w', BetterAuth::readSessionCookie(['sid' => 'w'], cookieName: 'sid'));
        // PHP rewrites `.` to `_` in $_COOKIE names.
        $this->assertSame('u', BetterAuth::readSessionCookie(['better-auth_session_token' => 'u']));
        $this->assertNull(BetterAuth::readSessionCookie('x=1'));
        $this->assertNull(BetterAuth::readSessionCookie(null));
        $this->assertSame(
            'a=b; Path=/; Domain=.example.com; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly; Secure; SameSite=Lax',
            (new BridgeCookie('a', 'b', domain: '.example.com', httpOnly: true, sameSite: 'Lax', secure: true, maxAge: 0))->toHeader(),
        );
    }

    /**
     * @param list<ExchangeResult> $results
     * @param list<array{0:string,1:?string,2:?string}> $calls
     */
    private static function fakeBridge(array $results, array &$calls, array $options = []): SessionBridge
    {
        return new SessionBridge(
            self::client(new MockClient()),
            ...$options,
            exchange: static function (Client $c, string $token, ?string $ip, ?string $ua) use (&$results, &$calls): ExchangeResult {
                $calls[] = [$token, $ip, $ua];

                return array_shift($results);
            },
        );
    }

    public function testBridgeDecisions(): void
    {
        $calls = [];
        $bridge = self::fakeBridge([
            new ExchangeResult(ExchangeResult::SESSION, jwt: 'jwt1', refreshToken: 'rt1'),
            new ExchangeResult(ExchangeResult::NEEDS_MFA, attemptId: 'sia_1', status: 'needs_mfa_enrollment'),
            ExchangeResult::refused('expired', 400),
            ExchangeResult::refused('closed', 403),
            ExchangeResult::refused('rate_limited', 429),
        ], $calls, ['betterAuthCookieDomain' => '.example.com']);

        $this->assertSame(BridgeDecision::NONE, $bridge->decide('a=1')->action);
        $this->assertSame(BridgeDecision::NONE, $bridge->decide('better-auth.session_token=t; __session=x')->action);
        $this->assertSame(BridgeDecision::NONE, $bridge->decide(['better-auth.session_token' => 't', '__atlas_rt' => 'y'])->action);
        $this->assertSame([], $calls, 'no exchange without a Better Auth cookie or with an Atlas session');

        $d = $bridge->decide('better-auth.session_token=tok.sig', '192.0.2.1', 'UA/1');
        $this->assertSame(BridgeDecision::SIGNED_IN, $d->action);
        $this->assertSame([['tok.sig', '192.0.2.1', 'UA/1']], $calls);
        $headers = $d->setCookieHeaders();
        $this->assertSame('__session=jwt1; Path=/; Secure; SameSite=Lax', $headers[0]);
        $this->assertSame('__atlas_rt=rt1; Path=/; HttpOnly; Secure; SameSite=Lax', $headers[1]);
        $this->assertCount(2 + 8, $headers);
        $this->assertContains('__Secure-better-auth.session_token=; Path=/; Domain=.example.com; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Secure', $headers);
        $this->assertContains('better-auth.session_token=; Path=/; Domain=.example.com; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT', $headers);

        $d = $bridge->decide('better-auth.session_token=t2');
        $this->assertSame([BridgeDecision::NEEDS_MFA, 'sia_1', 'needs_mfa_enrollment', 8], [$d->action, $d->attemptId, $d->status, count($d->cookies)]);
        $this->assertSame(
            '/sign-in?next=%2Fx&__atlas_attempt=sia_1&__atlas_status=needs_mfa_enrollment#top',
            $d->continuationUrl('/sign-in?next=%2Fx&__atlas_attempt=old&__atlas_status=old#top'),
        );

        $d = $bridge->decide('better-auth.session_token=t3');
        $this->assertSame([BridgeDecision::REFUSED, 'expired', 8], [$d->action, $d->outcome, count($d->cookies)]);
        $d = $bridge->decide('better-auth.session_token=t4');
        $this->assertSame([BridgeDecision::REFUSED, 'closed', 0], [$d->action, $d->outcome, count($d->cookies)]);
        $d = $bridge->decide('better-auth.session_token=t5');
        $this->assertSame([BridgeDecision::REFUSED, 'rate_limited', 0], [$d->action, $d->outcome, count($d->cookies)]);

        $calls = [];
        $custom = self::fakeBridge([new ExchangeResult(ExchangeResult::SESSION, jwt: 'j', refreshToken: 'r')], $calls, ['cookieName' => 'sid', 'secure' => false]);
        $d = $custom->decide('sid=abc');
        $this->assertSame([['abc', null, null]], $calls);
        $this->assertSame('__session=j; Path=/; SameSite=Lax', $d->setCookieHeaders()[0]);
        $this->assertCount(2 + 8 + 2, $d->cookies);
    }

    private function handler(int $status = 200): RequestHandlerInterface
    {
        return new class ($status) implements RequestHandlerInterface {
            public int $calls = 0;
            public ?ServerRequestInterface $received = null;

            public function __construct(private readonly int $status)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->calls++;
                $this->received = $request;

                return new Response($this->status, ['Set-Cookie' => 'app=1'], 'ok');
            }
        };
    }

    public function testPsr15BridgeMiddleware(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['object' => 'session_tokens', 'jwt' => 'jwt9', 'refresh_token' => 'rt9']);
        $mock->push(200, ['object' => 'sign_in_attempt', 'id' => 'sia_7', 'status' => 'needs_second_factor']);
        $mock->push(400, ['errors' => [['code' => 'VERIFICATION_FAILED', 'message' => 'x', 'meta' => ['bridge_outcome' => 'invalid']]]]);
        $mock->push(503, 'down');
        $mw = new BetterAuthBridgeMiddleware(new SessionBridge(self::client($mock)), '/sign-in', new HttpFactory());

        // signed in: 303 back to the same URL, the exchange forwarded the end user's IP and UA
        $handler = $this->handler();
        $req = (new ServerRequest('GET', 'https://app.test/dash?x=1', ['User-Agent' => 'UA/2', 'X-Forwarded-For' => '203.0.113.5, 10.0.0.1'], null, '1.1', ['REMOTE_ADDR' => '10.0.0.1']))
            ->withCookieParams(['better-auth.session_token' => 'tok.sig']);
        $res = $mw->process($req, $handler);
        $this->assertSame(303, $res->getStatusCode());
        $this->assertSame('https://app.test/dash?x=1', $res->getHeaderLine('Location'));
        $this->assertSame('no-store', $res->getHeaderLine('Cache-Control'));
        $this->assertSame(0, $handler->calls);
        $cookies = $res->getHeader('Set-Cookie');
        $this->assertSame('__session=jwt9; Path=/; Secure; SameSite=Lax', $cookies[0]);
        $this->assertCount(10, $cookies);
        $sent = $mock->lastRequest();
        $this->assertSame(self::API . '/v1/migrations/better_auth/exchange', (string) $sent->getUri());
        $this->assertSame('203.0.113.5', $sent->getHeaderLine('X-Atlas-Client-IP'));
        $this->assertSame('UA/2', $sent->getHeaderLine('X-Atlas-Client-User-Agent'));
        $this->assertSame(['token' => 'tok.sig'], json_decode((string) $sent->getBody(), true));

        // MFA owed: continuation on the sign-in page
        $res = $mw->process((new ServerRequest('GET', '/p', [], null, '1.1', ['REMOTE_ADDR' => '198.51.100.7']))->withCookieParams(['better-auth.session_token' => 't2']), $handler);
        $this->assertSame('/sign-in?__atlas_attempt=sia_7&__atlas_status=needs_second_factor', $res->getHeaderLine('Location'));
        $this->assertSame('198.51.100.7', $mock->lastRequest()->getHeaderLine('X-Atlas-Client-IP'));

        // final refusal: the handler runs and the response clears the Better Auth cookies
        $res = $mw->process((new ServerRequest('GET', '/p'))->withCookieParams(['better-auth.session_token' => 't3']), $handler);
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame(1, $handler->calls);
        $this->assertSame('app=1', $res->getHeader('Set-Cookie')[0]);
        $this->assertCount(9, $res->getHeader('Set-Cookie'));

        // an outage keeps the cookie for a later retry
        $res = $mw->process((new ServerRequest('GET', '/p'))->withCookieParams(['better-auth.session_token' => 't4']), $handler);
        $this->assertSame(['app=1'], $res->getHeader('Set-Cookie'));

        // POST and requests without the cookie never call Atlas
        $count = $mock->requestCount();
        $mw->process((new ServerRequest('POST', '/p'))->withCookieParams(['better-auth.session_token' => 't5']), $handler);
        $mw->process(new ServerRequest('GET', '/p', ['Cookie' => 'other=1']), $handler);
        $this->assertSame($count, $mock->requestCount());
        $this->assertSame(4, $handler->calls);
    }

    public function testAuthenticationMiddlewareBridgeOption(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['object' => 'session_tokens', 'jwt' => 'jwtA', 'refresh_token' => 'rtA']);
        $mock->push(400, ['errors' => [['code' => 'VERIFICATION_EXPIRED', 'message' => 'x', 'meta' => ['bridge_outcome' => 'expired']]]]);
        $factory = new HttpFactory();
        $bridge = new BetterAuthBridgeMiddleware(new SessionBridge(self::client($mock)), null, $factory, static fn (): string => '192.0.2.44');
        $mw = new AtlasAuthenticationMiddleware($this->verifier(), $factory, $factory, 'atlas', $bridge);
        $handler = $this->handler();

        $res = $mw->process(new ServerRequest('GET', '/home', ['Cookie' => 'better-auth.session_token=abc']), $handler);
        $this->assertSame(303, $res->getStatusCode());
        $this->assertSame('/home', $res->getHeaderLine('Location'));
        $this->assertSame('192.0.2.44', $mock->lastRequest()->getHeaderLine('X-Atlas-Client-IP'));

        // refused: the 401 still clears the dead Better Auth cookies
        $res = $mw->process(new ServerRequest('GET', '/home', ['Cookie' => 'better-auth.session_token=old']), $handler);
        $this->assertSame(401, $res->getStatusCode());
        $this->assertCount(8, $res->getHeader('Set-Cookie'));

        // a valid Atlas session flows through untouched (no exchange)
        $count = $mock->requestCount();
        $res = $mw->process(new ServerRequest('GET', '/home', ['Authorization' => 'Bearer ' . $this->mint()]), $handler);
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame($count, $mock->requestCount());
    }

    // ---- Better Auth JWTs during the cutover ----------------------------------------------

    private const OLD = 'https://old-app.test';

    private static function b64(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    /**
     * A key pair for an algorithm: [sign(input): signature, public JWK].
     *
     * @return array{0:\Closure(string):string,1:array<string,mixed>}
     */
    private static function keyFor(string $alg, string $kid): array
    {
        if ($alg === 'EdDSA') {
            $pair = sodium_crypto_sign_keypair();
            $secret = sodium_crypto_sign_secretkey($pair);

            return [
                static fn (string $in): string => sodium_crypto_sign_detached($in, $secret),
                ['kty' => 'OKP', 'crv' => 'Ed25519', 'x' => self::b64(sodium_crypto_sign_publickey($pair)), 'kid' => $kid, 'alg' => 'EdDSA'],
            ];
        }
        if ($alg === 'ES256' || $alg === 'ES512') {
            [$curve, $size, $crv, $hash] = $alg === 'ES256' ? ['prime256v1', 32, 'P-256', OPENSSL_ALGO_SHA256] : ['secp521r1', 66, 'P-521', OPENSSL_ALGO_SHA512];
            $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => $curve]);
            $d = openssl_pkey_get_details($key)['ec'];

            return [
                static function (string $in) use ($key, $size, $hash): string {
                    openssl_sign($in, $der, $key, $hash);

                    return self::derToRaw($der, $size);
                },
                ['kty' => 'EC', 'crv' => $crv, 'x' => self::b64(str_pad($d['x'], $size, "\0", STR_PAD_LEFT)), 'y' => self::b64(str_pad($d['y'], $size, "\0", STR_PAD_LEFT)), 'kid' => $kid],
            ];
        }
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $rsa = openssl_pkey_get_details($key)['rsa'];
        $jwk = ['kty' => 'RSA', 'n' => self::b64($rsa['n']), 'e' => self::b64($rsa['e']), 'kid' => $kid];
        if ($alg === 'RS256') {
            return [static function (string $in) use ($key): string {
                openssl_sign($in, $sig, $key, OPENSSL_ALGO_SHA256);

                return $sig;
            }, $jwk];
        }
        // PS256: signed by the openssl CLI, independent of the SDK's PSS verification.
        openssl_pkey_export($key, $pem);

        return [static function (string $in) use ($pem): string {
            $dir = sys_get_temp_dir() . '/atlas-pss-' . bin2hex(random_bytes(6));
            mkdir($dir);
            file_put_contents("{$dir}/k.pem", $pem);
            file_put_contents("{$dir}/in", $in);
            exec(sprintf('openssl dgst -sha256 -sigopt rsa_padding_mode:pss -sigopt rsa_pss_saltlen:32 -sign %s -out %s %s 2>&1', escapeshellarg("{$dir}/k.pem"), escapeshellarg("{$dir}/sig"), escapeshellarg("{$dir}/in")), $out, $code);
            $sig = $code === 0 ? (string) file_get_contents("{$dir}/sig") : '';
            array_map('unlink', glob("{$dir}/*") ?: []);
            rmdir($dir);

            return $sig;
        }, $jwk];
    }

    private static function derToRaw(string $der, int $size): string
    {
        $offset = 2 + ((ord($der[1]) & 0x80) !== 0 ? (ord($der[1]) & 0x7f) : 0);
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$offset + 1]);
            $int = substr($der, $offset + 2, $len);
            $out .= str_pad(ltrim($int, "\0"), $size, "\0", STR_PAD_LEFT);
            $offset += 2 + $len;
        }

        return $out;
    }

    /**
     * @param \Closure(string):string $sign
     * @param array<string,mixed>     $claims
     */
    private static function token(string $alg, string $kid, \Closure $sign, array $claims): string
    {
        $input = self::b64((string) json_encode(['alg' => $alg, 'kid' => $kid, 'typ' => 'JWT'])) . '.' . self::b64((string) json_encode($claims));

        return $input . '.' . self::b64($sign($input));
    }

    /**
     * @param list<LegacyIssuer> $issuers
     */
    private function legacyVerifier(MockClient $mock, array $issuers, ?string $secretKey = 'sk_test_lookup'): SessionVerifier
    {
        return new SessionVerifier(
            jwksUrl: 'https://issuer.test/.well-known/jwks.json',
            issuer: 'https://issuer.test',
            authorizedParties: ['https://app.test'],
            secretKey: $secretKey,
            bapiBaseUrl: self::API,
            httpClient: $mock,
            requestFactory: new HttpFactory(),
            streamFactory: new HttpFactory(),
            now: fn (): int => $this->harnessNow,
            legacyIssuers: $issuers,
        );
    }

    public function testLegacyTokensOfEveryBetterAuthAlgorithmVerify(): void
    {
        foreach (LegacyIssuer::BETTER_AUTH_ALGS as $alg) {
            [$sign, $jwk] = self::keyFor($alg, "k-{$alg}");
            $mock = new MockClient();
            $mock->push(200, ['data' => [['id' => 'user_atlas_1']]]);
            $v = $this->legacyVerifier($mock, [new LegacyIssuer(self::OLD, ($this->harnessNow + 86400) * 1000, jwks: ['keys' => [$jwk]])]);
            $tok = self::token($alg, "k-{$alg}", $sign, ['iss' => self::OLD, 'sub' => 'ba_user_1', 'exp' => $this->harnessNow + 600, 'iat' => $this->harnessNow, 'email' => 'a@b.test']);
            $res = $v->verify($tok);
            $this->assertTrue($res->ok, "{$alg} should verify");
            $this->assertSame('user_atlas_1', $res->claims['sub']);
            $this->assertSame('ba_user_1', $res->claims['external_id']);
            $this->assertSame(['kind' => 'better_auth', 'issuer' => self::OLD, 'sub' => 'ba_user_1'], $res->claims['legacy']);
            $this->assertSame('a@b.test', $res->claims['email']);
            $lookup = $mock->lastRequest();
            $this->assertSame(self::API . '/v1/users?external_id=ba_user_1&limit=1', (string) $lookup->getUri());
            $this->assertSame('Bearer sk_test_lookup', $lookup->getHeaderLine('Authorization'));

            // tampered payload fails
            [$h, , $s] = explode('.', $tok);
            $forged = $h . '.' . self::b64((string) json_encode(['iss' => self::OLD, 'sub' => 'ba_admin', 'exp' => $this->harnessNow + 600])) . '.' . $s;
            $this->assertSame('invalid', $v->verify($forged)->reason, "{$alg} forged");
        }
    }

    public function testLegacyRulesWindowExpiryAudienceAndKeys(): void
    {
        [$sign, $jwk] = self::keyFor('EdDSA', 'ed1');
        $until = ($this->harnessNow + 3600) * 1000;
        $mock = new MockClient();
        $mock->push(200, ['data' => [['id' => 'user_2']]]);
        $v = $this->legacyVerifier($mock, [new LegacyIssuer(self::OLD, $until, jwks: ['keys' => [$jwk]], audience: 'https://api.old.test')]);
        $base = ['iss' => self::OLD, 'sub' => 'ba_2', 'aud' => 'https://api.old.test', 'exp' => $this->harnessNow + 60];

        $this->assertTrue($v->verify(self::token('EdDSA', 'ed1', $sign, $base))->ok);
        // the sub → Atlas id mapping is cached
        $this->assertTrue($v->verify(self::token('EdDSA', 'ed1', $sign, $base + ['jti' => 'again']))->ok);
        $this->assertSame(1, $mock->requestCount());

        $this->assertSame('invalid', $v->verify(self::token('EdDSA', 'ed1', $sign, array_diff_key($base, ['exp' => 1])))->reason, 'exp is required');
        $this->assertSame('invalid', $v->verify(self::token('EdDSA', 'ed1', $sign, ['exp' => $this->harnessNow - 10] + $base))->reason, 'expired');
        $this->assertTrue($v->verify(self::token('EdDSA', 'ed1', $sign, ['exp' => $this->harnessNow - 3] + $base))->ok, 'inside the leeway');
        $this->assertSame('invalid', $v->verify(self::token('EdDSA', 'ed1', $sign, ['nbf' => $this->harnessNow + 60] + $base))->reason, 'not yet valid');
        $this->assertSame('invalid', $v->verify(self::token('EdDSA', 'ed1', $sign, ['aud' => 'https://elsewhere.test'] + $base))->reason, 'audience');
        $this->assertSame('unauthorized_party', $v->verify(self::token('EdDSA', 'ed1', $sign, ['azp' => 'https://evil.test'] + $base))->reason, 'azp allowlist');
        $this->assertSame('invalid', $v->verify(self::token('EdDSA', 'other-kid', $sign, $base))->reason, 'unknown kid');
        $this->assertSame('invalid', $v->verify(self::token('HS256', 'ed1', static fn (string $in): string => hash_hmac('sha256', $in, 'x', true), $base))->reason, 'HS256 never');
        $this->assertSame('invalid', $v->verify(self::token('none', 'ed1', static fn (): string => '', $base))->reason, 'alg none never');
        // the id claim stands in for a missing sub (Better Auth's default payload is the user)
        $this->assertSame('ba_2', $v->verify(self::token('EdDSA', 'ed1', $sign, ['id' => 'ba_2'] + array_diff_key($base, ['sub' => 1])))->claims['external_id']);

        // after the window, the issuer is foreign: the Atlas path refuses it (no Atlas key signs it)
        $late = new MockClient();
        $late->push(200, $this->keys()->jwks);
        $after = new SessionVerifier(
            jwksUrl: 'https://issuer.test/.well-known/jwks.json',
            issuer: 'https://issuer.test',
            secretKey: 'sk',
            httpClient: $late,
            requestFactory: new HttpFactory(),
            streamFactory: new HttpFactory(),
            now: fn (): int => $this->harnessNow + 7200,
            legacyIssuers: [new LegacyIssuer(self::OLD, $until, jwks: ['keys' => [$jwk]])],
        );
        $this->assertFalse($after->verify(self::token('EdDSA', 'ed1', $sign, ['exp' => $this->harnessNow + 9000] + $base))->ok);
    }

    public function testLegacyJwksUrlSubjectFailuresAndResolver(): void
    {
        [$sign, $jwk] = self::keyFor('ES256', 'ec1');
        $mock = new MockClient();
        $mock->push(200, ['keys' => [$jwk]]);
        $mock->push(500, 'down');
        $mock->push(200, ['data' => []]);
        $v = $this->legacyVerifier($mock, [new LegacyIssuer(self::OLD, '2099-01-01T00:00:00Z', jwksUrl: 'https://old-app.test/api/auth/jwks')]);
        $tok = self::token('ES256', 'ec1', $sign, ['iss' => self::OLD, 'sub' => 'ba_3', 'exp' => $this->harnessNow + 60]);
        $this->assertSame('invalid', $v->verify($tok)->reason, 'a failed lookup refuses');
        $this->assertSame('https://old-app.test/api/auth/jwks', (string) $mock->requests[0]->getUri());
        $this->assertSame('invalid', $v->verify($tok)->reason, 'a failure is not cached: looked up again, no such user');
        $this->assertSame(3, $mock->requestCount());
        $this->assertSame('invalid', $v->verify($tok)->reason, 'a miss is cached');
        $this->assertSame(3, $mock->requestCount());

        $noKeys = new MockClient();
        $noKeys->push(500, 'down');
        $v = $this->legacyVerifier($noKeys, [new LegacyIssuer(self::OLD, '2099-01-01T00:00:00Z', jwksUrl: 'https://old-app.test/api/auth/jwks')]);
        $this->assertSame('no_keys', $v->verify($tok)->reason);

        $seen = [];
        $v = $this->legacyVerifier(new MockClient(), [new LegacyIssuer(
            self::OLD,
            new \DateTimeImmutable('2099-01-01'),
            jwks: ['keys' => [$jwk]],
            resolveSubject: static function (string $sub, array $claims) use (&$seen): ?string {
                $seen[] = $sub;

                return $sub === 'ba_3' ? 'user_mapped' : null;
            },
        )], null);
        $this->assertSame('user_mapped', $v->verify($tok)->claims['sub']);
        $this->assertSame('invalid', $v->verify(self::token('ES256', 'ec1', $sign, ['iss' => self::OLD, 'sub' => 'ba_x', 'exp' => $this->harnessNow + 60]))->reason);
        $this->assertSame(['ba_3', 'ba_x'], $seen);

        // Atlas's own tokens still verify alongside
        $atlas = new MockClient();
        $atlas->push(200, $this->keys()->jwks);
        $v = $this->legacyVerifier($atlas, [new LegacyIssuer(self::OLD, '2099-01-01', jwks: ['keys' => [$jwk]])]);
        $this->assertTrue($v->verify($this->mint(['azp' => 'https://app.test']))->ok);
    }

    public function testLegacyConfigurationErrors(): void
    {
        $jwks = ['keys' => []];
        foreach ([
            static fn () => new LegacyIssuer('', '2099-01-01', jwks: $jwks),
            static fn () => new LegacyIssuer(self::OLD, '2099-01-01'),
            static fn () => new LegacyIssuer(self::OLD, '2099-01-01', jwks: $jwks, jwksUrl: 'https://x.test'),
            static fn () => new LegacyIssuer(self::OLD, '', jwks: $jwks),
            static fn () => new LegacyIssuer(self::OLD, 'not a date', jwks: $jwks),
            static fn () => new LegacyIssuer(self::OLD, '2099-01-01', jwks: $jwks, algorithms: ['HS256']),
            fn () => $this->legacyVerifier(new MockClient(), [new LegacyIssuer('https://issuer.test', '2099-01-01', jwks: $jwks)]),
            fn () => $this->legacyVerifier(new MockClient(), [new LegacyIssuer(self::OLD, '2099-01-01', jwks: $jwks)], null),
        ] as $i => $make) {
            try {
                $make();
                $this->fail("case {$i} should be refused");
            } catch (ConfigException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(4_070_908_800_000, LegacyIssuer::toMs('2099-01-01T00:00:00Z'));
        $this->assertSame(self::OLD, LegacyIssuerVerifier::peekIssuer(self::token('EdDSA', 'k', static fn (): string => 'x', ['iss' => self::OLD])));
        $this->assertNull(LegacyIssuerVerifier::peekIssuer('nope'));
    }
}
