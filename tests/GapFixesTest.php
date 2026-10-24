<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\AtlasIdTokens;
use Atlas\Client;
use Atlas\Constants;
use Atlas\ErrorCode;
use Atlas\Exception\AtlasApiException;
use Atlas\Exception\PermissionException;
use Atlas\OidcClaims;
use Atlas\PartnerLink\PartnerLinkRequests;
use Atlas\Tests\Support\MockClient;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

/** OIDC org/SSO claims, partner link requests (owner and partner sides), key lock fields, ORIGIN_NOT_ALLOWED, failing_since, atlas_id_tokens. */
final class GapFixesTest extends TestCase
{
    private const BASE = 'https://api.atlas.test';

    /**
     * @return list<array{0:string,1:string,2:array<string,mixed>|null,3:\Closure(Client):mixed}>
     */
    private static function operations(): array
    {
        return [
            ['GET', '/v1/partner_links/requests?direction=inbound&status=pending%2Cdelivery_failed', null, static fn (Client $c) => $c->partnerLinks->listRequests(['direction' => 'inbound', 'status' => ['pending', 'delivery_failed']])],
            ['GET', '/v1/partner_links/requests/plr_1', null, static fn (Client $c) => $c->partnerLinks->getRequest('plr_1')],
            ['POST', '/v1/partner_links/requests/plr_1/approve', ['scopes' => ['privacy:execute']], static fn (Client $c) => $c->partnerLinks->approveRequest('plr_1', ['scopes' => ['privacy:execute']])],
            ['POST', '/v1/partner_links/requests/plr_1/deny', null, static fn (Client $c) => $c->partnerLinks->denyRequest('plr_1')],
            ['PATCH', '/v1/partner_links/pl_1', ['cert_fingerprints' => ['abc']], static fn (Client $c) => $c->partnerLinks->setCertificates('pl_1', ['abc'])],
            ['POST', '/v1/partner_links/outbound/cm/request', ['scopes' => ['privacy:execute']], static fn (Client $c) => $c->partnerLinks->requestOutbound('cm', ['scopes' => ['privacy:execute']])],
            ['POST', '/v1/secret_keys', ['name' => 'ci', 'owner' => 'user:u1', 'expires_at' => 1, 'allowed_origins' => ['https://app.example.com']], static fn (Client $c) => $c->secretKeys->create(['name' => 'ci', 'owner' => 'user:u1', 'expires_at' => 1, 'allowed_origins' => ['https://app.example.com']])],
            ['POST', '/v1/webhook_endpoints/we1/replay', ['since' => Constants::WEBHOOK_REPLAY_SINCE_FAILING], static fn (Client $c) => $c->webhooks->endpoints->replay('we1', ['since' => Constants::WEBHOOK_REPLAY_SINCE_FAILING])],
            ['POST', '/v1/webhook_endpoints/we1/redeliver', ['since' => 'failing_since'], static fn (Client $c) => $c->webhooks->endpoints->redeliverWindow('we1', ['since' => 'failing_since'])],
        ];
    }

    public function testRoutes(): void
    {
        foreach (self::operations() as [$verb, $path, $body, $call]) {
            $mock = new MockClient();
            $mock->push(200, ['ok' => true]);
            $call(new Client('atlas_live_sk_123', self::BASE, $mock));
            $request = $mock->lastRequest();
            $this->assertSame($verb, $request->getMethod(), $path);
            $this->assertSame(self::BASE . $path, (string) $request->getUri());
            $sent = (string) $request->getBody();
            if ($body === null) {
                $this->assertSame('', $sent, "{$verb} {$path}");
            } else {
                $this->assertSame($body, json_decode($sent, true), "{$verb} {$path}");
            }
        }
    }

    public function testOriginNotAllowed(): void
    {
        $mock = new MockClient();
        $mock->push(403, ['errors' => [['code' => 'ORIGIN_NOT_ALLOWED', 'message' => 'no']]]);
        try {
            (new Client('sk_test_1', self::BASE, $mock))->users->get('u');
            $this->fail('expected an exception');
        } catch (PermissionException $e) {
            $this->assertTrue($e->isOriginNotAllowed());
            $this->assertTrue($e->hasCode(ErrorCode::ORIGIN_NOT_ALLOWED));
            $this->assertFalse($e->isStepUpRequired());
            $this->assertSame('ORIGIN_NOT_ALLOWED', ErrorCode::ORIGIN_NOT_ALLOWED);
        }
    }

    public function testOidcClaims(): void
    {
        $claims = [
            'sub' => 'u1', 'org_id' => 'org_1', 'org_slug' => 'acme', 'org_role' => 'admin', 'org_permissions' => ['org:read', 3],
            'orgs' => [['id' => 'org_1', 'slug' => 'acme', 'role' => 'admin'], ['slug' => 'x']],
            'sso_connection_id' => 'sso_1', 'sso_organization_id' => 'org_1', 'sso_connection_type' => 'saml', 'sso_tenant_id' => 'tid', 'sso_issuer' => 'https://idp', 'sso_domain' => 'acme.com',
        ];
        $this->assertSame(['id' => 'org_1', 'slug' => 'acme', 'role' => 'admin', 'permissions' => ['org:read']], OidcClaims::organization($claims));
        $this->assertSame([['id' => 'org_1', 'slug' => 'acme', 'role' => 'admin']], OidcClaims::organizations($claims));
        $this->assertSame(['connection_id' => 'sso_1', 'organization_id' => 'org_1', 'type' => 'saml', 'tenant_id' => 'tid', 'issuer' => 'https://idp', 'domain' => 'acme.com'], OidcClaims::sso($claims));
        $this->assertNull(OidcClaims::organization(['sub' => 'u']));
        $this->assertNull(OidcClaims::organizations(['sub' => 'u']));
        $this->assertNull(OidcClaims::sso(['sub' => 'u']));
        $this->assertSame('organizations', OidcClaims::SCOPE_ORGANIZATIONS);
        $this->assertSame('organizations', Constants::OIDC_SCOPE_ORGANIZATIONS);

        $url = OidcClaims::authorizeUrlWithOrganization('https://atlasauth.net/oauth2/authorize?client_id=c', 'acme');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame(['client_id' => 'c', 'organization' => 'acme'], $q);
        $again = OidcClaims::authorizeUrlWithOrganization($url, 'other');
        parse_str((string) parse_url($again, PHP_URL_QUERY), $q2);
        $this->assertSame('other', $q2['organization']);
    }

    public function testPartnerSideRequestAndDelivery(): void
    {
        $mock = new MockClient();
        $mock->push(201, ['id' => 'plr_1', 'request_secret' => 'rs_1', 'status' => 'pending']);
        $requests = new PartnerLinkRequests('https://api.example.test', $mock, new HttpFactory());
        $out = $requests->create(['partner' => 'acme', 'resource' => 'ins_1', 'scopes' => ['privacy:execute'], 'callback_url' => 'https://acme.test/cb']);
        $this->assertSame('rs_1', $out['request_secret']);
        $req = $mock->lastRequest();
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('https://api.example.test/v1/partner_link_requests', (string) $req->getUri());
        $this->assertFalse($req->hasHeader('Authorization'));
        $this->assertSame('https://acme.test/cb', json_decode((string) $req->getBody(), true)['callback_url']);

        $mock->push(200, ['status' => 'approved']);
        $this->assertSame('approved', $requests->get('plr_1', 'rs_1')['status']);
        $this->assertSame('Bearer rs_1', $mock->lastRequest()->getHeaderLine('Authorization'));

        $mock->push(403, ['errors' => [['code' => 'PARTNER_LINK_UNAVAILABLE', 'message' => 'no']]]);
        try {
            $requests->create(['partner' => 'x']);
            $this->fail('expected an exception');
        } catch (AtlasApiException $e) {
            $this->assertTrue($e->hasCode(ErrorCode::PARTNER_LINK_UNAVAILABLE));
        }

        $delivery = json_encode(['object' => 'partner_link.credentials', 'request_id' => 'plr_1', 'client_id' => 'c', 'client_secret' => 's'], JSON_THROW_ON_ERROR);
        $ts = 1_757_937_600_000;
        $v1 = 'v1,' . base64_encode(hash_hmac('sha256', "evt_9.{$ts}.{$delivery}", 'rs_1', true));
        $headers = ['atlas-id' => 'evt_9', 'atlas-timestamp' => (string) $ts, 'atlas-signature' => $v1];
        $ok = PartnerLinkRequests::verifyDelivery($delivery, $headers, 'rs_1', $ts);
        $this->assertTrue($ok->verified);
        $this->assertSame('s', $ok->event['client_secret']);
        $this->assertFalse(PartnerLinkRequests::verifyDelivery($delivery, $headers, 'other', $ts)->verified);
        $url = 'https://acme.test/cb';
        $v2 = 'v2,' . base64_encode(hash_hmac('sha256', "evt_9.{$ts}.{$url}.{$delivery}", 'rs_1', true));
        $h2 = ['atlas-id' => 'evt_9', 'atlas-timestamp' => (string) $ts, 'atlas-signature' => $v2];
        $this->assertTrue(PartnerLinkRequests::verifyDelivery($delivery, $h2, 'rs_1', $ts, $url)->verified);
    }

    public function testBrowserAtlasIdTokensRoute(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['jwt' => 'j', 'expires_in' => 60, 'integration' => 'referalli', 'audience' => 'a', 'issuer' => 'i', 'jwks_uri' => 'u']);
        $tokens = new AtlasIdTokens('https://clerk.example.test/', 'pk_test_1', $mock, new HttpFactory());
        $out = $tokens->fetch('referalli', 'at_1', origin: 'https://app.example.com');
        $this->assertSame('j', $out['jwt']);
        $req = $mock->lastRequest();
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('https://clerk.example.test/v1/client/integrations/referalli/atlas_id_tokens?publishable_key=pk_test_1', (string) $req->getUri());
        $this->assertSame('Bearer at_1', $req->getHeaderLine('Authorization'));
        $this->assertSame('https://app.example.com', $req->getHeaderLine('Origin'));

        $mock->push(200, ['jwt' => 'j']);
        $tokens->fetch('referalli', 'at_1', dpopProof: 'proof');
        $this->assertSame('DPoP at_1', $mock->lastRequest()->getHeaderLine('Authorization'));
        $this->assertSame('proof', $mock->lastRequest()->getHeaderLine('DPoP'));

        $mock->push(403, ['errors' => []]);
        $this->assertNull($tokens->fetch('x', 'at_1'));
        $this->assertNull($tokens->fetch('x', ''));
    }
}
