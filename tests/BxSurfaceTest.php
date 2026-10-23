<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\Psr15\AtlasAccessTokenMiddleware;
use Atlas\Tests\Support\Keys;
use Atlas\Tests\Support\MockClient;
use Atlas\Verify\AccessTokenVerifier;
use Atlas\Verify\SessionVerifier;
use Atlas\Verify\SigningKeys;
use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * App roles, org teams/roles, timed bans, API key controls, billing providers and
 * subscription management, OAuth server administration, ES256 verification,
 * app-claim helpers and the access-token verifier + PSR-15 middleware.
 */
final class BxSurfaceTest extends TestCase
{
    /**
     * @return list<array{0:string,1:string,2:array<string,mixed>|null,3:\Closure(Client):mixed}>
     */
    private static function operations(): array
    {
        return [
            ['GET', '/v1/app_roles', null, static fn (Client $c) => $c->appRoles->list()],
            ['POST', '/v1/app_roles', ['key' => 'support', 'name' => 'Support'], static fn (Client $c) => $c->appRoles->create(['key' => 'support', 'name' => 'Support'])],
            ['GET', '/v1/app_roles/support', null, static fn (Client $c) => $c->appRoles->get('support')],
            ['PATCH', '/v1/app_roles/support', ['permissions' => ['a:b']], static fn (Client $c) => $c->appRoles->update('support', ['permissions' => ['a:b']])],
            ['DELETE', '/v1/app_roles/ar_1', null, static fn (Client $c) => $c->appRoles->delete('ar_1')],
            ['GET', '/v1/app_roles/ar_1/users?limit=5&offset=10', null, static fn (Client $c) => $c->appRoles->listUsers('ar_1', ['limit' => 5, 'offset' => 10])],
            ['GET', '/v1/users/u_1/app_roles', null, static fn (Client $c) => $c->appRoles->getForUser('u_1')],
            ['POST', '/v1/users/u_1/app_roles', ['add' => ['support']], static fn (Client $c) => $c->appRoles->setForUser('u_1', ['add' => ['support']])],
            ['DELETE', '/v1/users/u_1/app_roles/support', null, static fn (Client $c) => $c->appRoles->removeFromUser('u_1', 'support')],
            ['GET', '/v1/organizations/org_1/teams?user_id=u_1', null, static fn (Client $c) => $c->organizations->teams->list('org_1', ['user_id' => 'u_1'])],
            ['POST', '/v1/organizations/org_1/teams', ['name' => 'Core'], static fn (Client $c) => $c->organizations->teams->create('org_1', ['name' => 'Core'])],
            ['GET', '/v1/organizations/org_1/teams/tm_1', null, static fn (Client $c) => $c->organizations->teams->get('org_1', 'tm_1')],
            ['PATCH', '/v1/organizations/org_1/teams/tm_1', ['name' => 'P'], static fn (Client $c) => $c->organizations->teams->update('org_1', 'tm_1', ['name' => 'P'])],
            ['DELETE', '/v1/organizations/org_1/teams/tm_1', null, static fn (Client $c) => $c->organizations->teams->delete('org_1', 'tm_1')],
            ['GET', '/v1/organizations/org_1/teams/tm_1/members', null, static fn (Client $c) => $c->organizations->teams->listMembers('org_1', 'tm_1')],
            ['POST', '/v1/organizations/org_1/teams/tm_1/members', ['user_id' => 'u_2'], static fn (Client $c) => $c->organizations->teams->addMember('org_1', 'tm_1', 'u_2')],
            ['DELETE', '/v1/organizations/org_1/teams/tm_1/members/u_2', null, static fn (Client $c) => $c->organizations->teams->removeMember('org_1', 'tm_1', 'u_2')],
            ['GET', '/v1/organizations/org_1/roles?own=true', null, static fn (Client $c) => $c->organizations->roles->list('org_1', true)],
            ['POST', '/v1/organizations/org_1/roles', ['key' => 'editor', 'name' => 'Editor'], static fn (Client $c) => $c->organizations->roles->create('org_1', ['key' => 'editor', 'name' => 'Editor'])],
            ['PATCH', '/v1/organizations/org_1/roles/r_1', ['name' => 'W'], static fn (Client $c) => $c->organizations->roles->update('org_1', 'r_1', ['name' => 'W'])],
            ['DELETE', '/v1/organizations/org_1/roles/r_1?reassign_to=member', null, static fn (Client $c) => $c->organizations->roles->delete('org_1', 'r_1', 'member')],
            ['GET', '/v1/organizations/by_slug/acme', null, static fn (Client $c) => $c->organizations->getBySlug('acme')],
            ['GET', '/v1/organizations/check_slug?slug=acme', null, static fn (Client $c) => $c->organizations->checkSlug('acme')],
            ['POST', '/v1/organizations/org_1/invitations/inv_1/resend', null, static fn (Client $c) => $c->organizations->invitations->resend('org_1', 'inv_1')],
            ['GET', '/v1/users?query=ada&external_id=ext_1&order_by=-created_at', null, static fn (Client $c) => $c->users->list(['query' => 'ada', 'external_id' => 'ext_1', 'order_by' => '-created_at'])],
            ['POST', '/v1/users/u_1/ban', ['reason' => 'spam', 'expires_in_seconds' => 3600], static fn (Client $c) => $c->users->ban('u_1', ['reason' => 'spam', 'expires_in_seconds' => 3600])],
            ['POST', '/v1/users/u_1/ban', null, static fn (Client $c) => $c->users->ban('u_1')],
            ['POST', '/v1/api_keys/verify', ['secret' => 'ak_x', 'required_scopes' => ['read']], static fn (Client $c) => $c->apiKeys->verify('ak_x', ['read'])],
            ['GET', '/v1/billing/providers', null, static fn (Client $c) => $c->billing->listProviders()],
            ['GET', '/v1/billing/providers/polar', null, static fn (Client $c) => $c->billing->getProvider('polar')],
            ['PUT', '/v1/billing/providers/polar', ['environment' => 'test', 'config' => ['access_token' => 'x']], static fn (Client $c) => $c->billing->setProvider('polar', ['environment' => 'test', 'config' => ['access_token' => 'x']])],
            ['POST', '/v1/billing/providers/polar/test', null, static fn (Client $c) => $c->billing->testProvider('polar')],
            ['DELETE', '/v1/billing/providers/polar?force=true', null, static fn (Client $c) => $c->billing->deleteProvider('polar', true)],
            ['POST', '/v1/billing/subscriptions/sub_1/change', ['plan_id' => 'p_2', 'interval' => 'year'], static fn (Client $c) => $c->billing->changeSubscription('sub_1', ['plan_id' => 'p_2', 'interval' => 'year'])],
            ['POST', '/v1/billing/subscriptions/sub_1/cancel', ['immediately' => true], static fn (Client $c) => $c->billing->cancelSubscription('sub_1', true)],
            ['POST', '/v1/billing/subscriptions/sub_1/restore', null, static fn (Client $c) => $c->billing->restoreSubscription('sub_1')],
            ['GET', '/v1/instance/signing_keys', null, static fn (Client $c) => $c->instanceSecurity->listSigningKeys()],
            ['POST', '/v1/instance/signing_keys/rotate', ['alg' => 'ES256'], static fn (Client $c) => $c->instanceSecurity->rotateSigningKey(['alg' => 'ES256'])],
            ['GET', '/v1/oauth_initial_access_tokens', null, static fn (Client $c) => $c->oauthInitialAccessTokens->list()],
            ['POST', '/v1/oauth_initial_access_tokens', ['max_uses' => 3], static fn (Client $c) => $c->oauthInitialAccessTokens->create(['max_uses' => 3])],
            ['DELETE', '/v1/oauth_initial_access_tokens/iat_1', null, static fn (Client $c) => $c->oauthInitialAccessTokens->revoke('iat_1')],
            ['PATCH', '/v1/scim_tokens/st_1', ['scopes' => ['read']], static fn (Client $c) => $c->scimTokens->update('st_1', ['scopes' => ['read']])],
            ['GET', '/v1/instance/hooks/before_user_create/calls?status=failed', null, static fn (Client $c) => $c->userHooks->listBeforeUserCreateCalls(['status' => 'failed'])],
            ['GET', '/v1/instance/referrals/calls/rc_1', null, static fn (Client $c) => $c->referrals->getCall('rc_1')],
        ];
    }

    public function testEveryNewOperationHitsItsRoute(): void
    {
        foreach (self::operations() as [$verb, $path, $body, $call]) {
            $mock = new MockClient();
            $mock->push(200, ['ok' => true]);
            $call(new Client('sk_test_123', 'https://api.atlas.test', $mock));
            $request = $mock->lastRequest();
            $this->assertSame($verb, $request->getMethod(), $path);
            $this->assertSame('https://api.atlas.test' . $path, (string) $request->getUri());
            $sent = (string) $request->getBody();
            if ($body === null) {
                $this->assertSame('', $sent, "{$verb} {$path} should send no body");
            } else {
                $this->assertSame($body, json_decode($sent, true), "{$verb} {$path} body");
            }
        }
    }

    // ── Verification ──────────────────────────────────────────────────────────

    private const ISSUER = 'https://issuer.test';
    private const AUD = 'https://api.test';

    private Keys $rsa;
    private string $ecPem;
    /** @var array{keys:array<int,array<string,string>>} */
    private array $jwks;

    protected function setUp(): void
    {
        $this->rsa = new Keys('rs-1');
        $ec = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->assertNotFalse($ec);
        openssl_pkey_export($ec, $pem);
        $this->ecPem = $pem;
        $d = openssl_pkey_get_details($ec);
        $b64 = static fn (string $b): string => rtrim(strtr(base64_encode(str_pad($b, 32, "\0", STR_PAD_LEFT)), '+/', '-_'), '=');
        $this->jwks = ['keys' => [
            ['kty' => 'EC', 'crv' => 'P-256', 'alg' => 'ES256', 'kid' => 'ec-1', 'x' => $b64($d['ec']['x']), 'y' => $b64($d['ec']['y'])],
            ...$this->rsa->jwks['keys'],
        ]];
    }

    /** @param array<string,mixed> $claims */
    private function es256(array $claims, string $kid = 'ec-1'): string
    {
        return JWT::encode($claims, $this->ecPem, 'ES256', $kid);
    }

    /** @return array<string,mixed> */
    private function session(array $extra = []): array
    {
        $now = time();

        return array_merge(['iss' => self::ISSUER, 'sub' => 'user_1', 'iat' => $now, 'exp' => $now + 300], $extra);
    }

    public function testSessionVerifierAcceptsEs256AndAppClaims(): void
    {
        $mock = new MockClient();
        $mock->push(200, $this->jwks);
        $v = new SessionVerifier(jwksUrl: self::ISSUER . '/jwks', issuer: self::ISSUER, httpClient: $mock,
            requestFactory: new HttpFactory(), streamFactory: new HttpFactory());

        $result = $v->verify($this->es256($this->session(['app_roles' => ['support'], 'app_permissions' => ['tickets:read']])));
        $this->assertTrue($result->ok, (string) $result->reason);
        $this->assertTrue($result->hasAppRole('support'));
        $this->assertFalse($result->hasAppRole('admin'));
        $this->assertTrue($result->hasAppPermission('tickets:read'));
        $this->assertFalse($result->has(['appPermission' => 'tickets:write']));
        $this->assertTrue($v->verify(JWT::encode($this->session(), $this->rsa->privateKeyPem, 'RS256', 'rs-1'))->ok);
        // ES256 under the RSA key's kid, and HS256, never verify.
        $this->assertFalse($v->verify($this->es256($this->session(), 'rs-1'))->ok);
        $this->assertFalse($v->verify(JWT::encode($this->session(), str_repeat('k', 64), 'HS256', 'ec-1'))->ok);
        $this->assertSame(['RS256', 'ES256'], SigningKeys::ALGS);
    }

    private function accessVerifier(): AccessTokenVerifier
    {
        $mock = new MockClient();
        $mock->push(200, $this->jwks);

        return new AccessTokenVerifier(
            issuer: self::ISSUER . '/',
            audience: self::AUD,
            requiredScopes: ['read'],
            resourceMetadataUrl: self::AUD . '/.well-known/oauth-protected-resource',
            httpClient: $mock,
            requestFactory: new HttpFactory(),
        );
    }

    /** @return array<string,mixed> */
    private function access(array $extra = []): array
    {
        return array_merge(['iss' => self::ISSUER, 'sub' => 'user_1', 'aud' => self::AUD, 'exp' => time() + 300,
            'token_use' => 'access_token', 'scope' => 'read write', 'client_id' => 'oc_1'], $extra);
    }

    public function testAccessTokenVerifier(): void
    {
        $v = $this->accessVerifier();
        $ok = $v->verify('Bearer ' . $this->es256($this->access()));
        $this->assertTrue($ok->ok, $ok->description);
        $this->assertSame('user_1', $ok->subject);
        $this->assertSame('oc_1', $ok->clientId);
        $this->assertSame(['read', 'write'], $ok->scopes);

        $none = $v->verify(null);
        $this->assertSame(401, $none->status);
        $this->assertNull($none->error);
        $this->assertSame('Bearer scope="read", resource_metadata="https://api.test/.well-known/oauth-protected-resource"', $none->wwwAuthenticate);
        $this->assertSame(400, $v->verify('Basic abc')->status);
        $wrong = $v->verify('Bearer ' . $this->es256($this->access(['aud' => 'https://other.test'])));
        $this->assertSame(401, $wrong->status);
        $this->assertStringContainsString('error="invalid_token"', $wrong->wwwAuthenticate);
        $this->assertFalse($v->verify('Bearer ' . $this->es256($this->access(['token_use' => 'session'])))->ok);
        $this->assertFalse($v->verify('Bearer ' . $this->es256($this->access(['cnf' => ['jkt' => 'x']])))->ok);
        $this->assertFalse($v->verify('Bearer ' . $this->es256($this->access(['exp' => time() - 600])))->ok);
        $short = $v->verify('Bearer ' . $this->es256($this->access(['scope' => 'write'])), ['admin']);
        $this->assertSame(403, $short->status);
        $this->assertSame('insufficient_scope', $short->error);
        $this->assertStringContainsString('scope="read admin"', $short->wwwAuthenticate);

        $this->assertSame('/.well-known/oauth-protected-resource/v1', AccessTokenVerifier::protectedResourceMetadataPath('https://api.test/v1/'));
        $doc = AccessTokenVerifier::protectedResourceMetadata(self::AUD, self::ISSUER . '/', ['read']);
        $this->assertSame([self::ISSUER], $doc['authorization_servers']);
    }

    public function testAccessTokenMiddleware(): void
    {
        $middleware = new AtlasAccessTokenMiddleware($this->accessVerifier());
        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], $request->getAttribute('atlas_access_token')->subject);
            }
        };
        $denied = $middleware->process(new ServerRequest('GET', '/items'), $handler);
        $this->assertSame(401, $denied->getStatusCode());
        $this->assertStringStartsWith('Bearer', $denied->getHeaderLine('WWW-Authenticate'));
        $ok = $middleware->process(
            (new ServerRequest('GET', '/items'))->withHeader('Authorization', 'Bearer ' . $this->es256($this->access())),
            $handler,
        );
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertSame('user_1', (string) $ok->getBody());
    }
}
