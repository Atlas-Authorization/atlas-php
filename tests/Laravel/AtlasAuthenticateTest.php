<?php

declare(strict_types=1);

namespace Atlas\Tests\Laravel;

use Atlas\Laravel\AtlasAuthenticate;
use Atlas\Laravel\RequirePermission;
use Atlas\Tests\Support\VerifierHarness;
use Atlas\Verify\VerifyResult;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * The Laravel route middleware, driven directly with an {@see Illuminate\Http\Request}
 * and a `Closure $next` — the focused-unit approach from the task, no full app
 * boot or orchestra/testbench needed. The verifier underneath is the real
 * {@see \Atlas\Verify\SessionVerifier} primed off a mocked PSR-18 client.
 */
final class AtlasAuthenticateTest extends TestCase
{
    use VerifierHarness;

    private function pass(): \Closure
    {
        return static fn (Request $request): Response => new Response('ok');
    }

    public function testBearerTokenPassesAndAttachesClaims(): void
    {
        $middleware = new AtlasAuthenticate($this->verifier());
        $request = Request::create('/protected', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->mint(['sub' => 'from_header']),
        ]);

        $response = $middleware->handle($request, $this->pass());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());

        $atlas = $request->attributes->get('atlas');
        $this->assertInstanceOf(VerifyResult::class, $atlas);
        $this->assertTrue($atlas->ok);
        $this->assertSame('from_header', $atlas->claims['sub']);

        // $request->user()-style access resolves to the same verified result.
        $this->assertInstanceOf(VerifyResult::class, $request->user());
        $this->assertSame('from_header', $request->user()->claims['sub']);
    }

    public function testSessionCookieIsAccepted(): void
    {
        $middleware = new AtlasAuthenticate($this->verifier());
        $request = Request::create('/protected', 'GET', cookies: ['__session' => $this->mint(['sub' => 'from_cookie'])]);

        $response = $middleware->handle($request, $this->pass());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('from_cookie', $request->attributes->get('atlas')->claims['sub']);
    }

    public function testMissingTokenIs401AndNeverCallsNext(): void
    {
        $middleware = new AtlasAuthenticate($this->verifier());
        $request = Request::create('/protected', 'GET');
        $called = false;

        $response = $middleware->handle($request, function () use (&$called): Response {
            $called = true;

            return new Response('ok');
        });

        $this->assertFalse($called, 'the gated route must not run without a session');
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('unauthenticated', $response->getData(true)['error']);
    }

    public function testInvalidTokenIs401(): void
    {
        $middleware = new AtlasAuthenticate($this->verifier());
        $request = Request::create('/protected', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->mint(['iss' => 'https://evil.test']),
        ]);

        $response = $middleware->handle($request, $this->pass());

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('invalid', $response->getData(true)['reason']);
    }

    public function testRequirePermissionAllowsWhenClaimPresent(): void
    {
        $auth = new AtlasAuthenticate($this->verifier());
        $perm = new RequirePermission();
        $request = Request::create('/billing', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->mint([
                'org_role' => 'admin',
                'org_permissions' => ['billing:read'],
            ]),
        ]);

        // atlas.auth runs first and attaches the result.
        $auth->handle($request, $this->pass());

        $allowed = $perm->handle($request, $this->pass(), 'billing:read');
        $this->assertSame(200, $allowed->getStatusCode());

        $byRole = $perm->handle($request, $this->pass(), 'role:admin');
        $this->assertSame(200, $byRole->getStatusCode());
    }

    public function testRequirePermissionDeniesWhenClaimMissing(): void
    {
        $auth = new AtlasAuthenticate($this->verifier());
        $perm = new RequirePermission();
        $request = Request::create('/billing', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->mint(['org_permissions' => ['billing:read']]),
        ]);
        $auth->handle($request, $this->pass());

        $response = $perm->handle($request, $this->pass(), 'billing:write');
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('forbidden', $response->getData(true)['error']);
    }

    public function testRequirePermissionDeniesWhenUnauthenticated(): void
    {
        $perm = new RequirePermission();
        $request = Request::create('/billing', 'GET'); // atlas.auth never ran

        $response = $perm->handle($request, $this->pass(), 'billing:read');
        $this->assertSame(403, $response->getStatusCode());
    }
}
