<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\Http;
use Atlas\Tests\Support\Keys;
use Atlas\Tests\Support\MockClient;
use Atlas\Verify\SessionVerifier;
use Atlas\WebhookEvents;
use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

/**
 * Tenant organizations, domain verification methods, trusted-JWT connections, setup-link
 * return URLs, chosen webhook secrets, the initial-secret reveal, path-mount domains and
 * previous issuers.
 */
final class EnterpriseTenancySurfaceTest extends TestCase
{
    /**
     * @return list<array{0:string,1:string,2:array<string,mixed>|null,3:\Closure(Client):mixed}>
     */
    private static function calls(): array
    {
        return [
            ['POST', '/v1/organizations', ['name' => 'Acme', 'slug' => 'acme', 'external_id' => 'cust_1', 'owner_email' => 'it@acme.example', 'role_mappings' => ['org:admin' => 'owner'], 'public_metadata' => ['plan' => 'pro']],
                static fn (Client $c) => $c->organizations->create(['name' => 'Acme', 'slug' => 'acme', 'external_id' => 'cust_1', 'owner_email' => 'it@acme.example', 'role_mappings' => ['org:admin' => 'owner'], 'public_metadata' => ['plan' => 'pro']])],
            ['GET', '/v1/organizations?external_id=cust_1&metadata%5Bplan%5D=pro&metadata%5Bregion%5D=eu', null,
                static fn (Client $c) => $c->organizations->list(['external_id' => 'cust_1', 'metadata' => ['plan' => 'pro', 'region' => 'eu']])],
            ['GET', '/v1/organizations/by_domain/acme.example', null, static fn (Client $c) => $c->organizations->findByDomain('acme.example')],
            ['GET', '/v1/organizations/by_domain/it%40acme.example?include_unverified=true', null, static fn (Client $c) => $c->organizations->findByDomain('it@acme.example', true)],
            ['GET', '/v1/organizations/org_1/policy', null, static fn (Client $c) => $c->organizations->getPolicy('org_1')],
            ['POST', '/v1/organizations/org_1/domains/dom_1/verify', null, static fn (Client $c) => $c->organizations->domains->verify('org_1', 'dom_1')],
            ['POST', '/v1/organizations/org_1/domains/dom_1/verify', ['method' => 'email', 'code' => '123456'], static fn (Client $c) => $c->organizations->domains->verify('org_1', 'dom_1', ['method' => 'email', 'code' => '123456'])],
            ['POST', '/v1/organizations/org_1/domains/dom_1/verification_email', ['address' => 'hostmaster'], static fn (Client $c) => $c->organizations->domains->sendVerificationEmail('org_1', 'dom_1', 'hostmaster')],
            ['POST', '/v1/organizations/org_1/domains/dom_1/verification_email', [], static fn (Client $c) => $c->organizations->domains->sendVerificationEmail('org_1', 'dom_1')],
            ['GET', '/v1/jwt_connections', null, static fn (Client $c) => $c->jwtConnections->list()],
            ['GET', '/v1/jwt_connections/jwtc_1', null, static fn (Client $c) => $c->jwtConnections->get('jwtc_1')],
            ['POST', '/v1/jwt_connections', ['jwks_uri' => 'https://idp.example.com/jwks', 'issuer' => 'https://idp.example.com', 'audience' => 'atlas'], static fn (Client $c) => $c->jwtConnections->create(['jwks_uri' => 'https://idp.example.com/jwks', 'issuer' => 'https://idp.example.com', 'audience' => 'atlas'])],
            ['PATCH', '/v1/jwt_connections/jwtc_1', ['status' => 'active'], static fn (Client $c) => $c->jwtConnections->update('jwtc_1', ['status' => 'active'])],
            ['DELETE', '/v1/jwt_connections/jwtc_1', null, static fn (Client $c) => $c->jwtConnections->delete('jwtc_1')],
            ['GET', '/v1/sso_onboarding_profiles', null, static fn (Client $c) => $c->ssoOnboarding->list()],
            ['GET', '/v1/sso_onboarding_profiles?name=Acme%20setup', null, static fn (Client $c) => $c->ssoOnboarding->list('Acme setup')],
            ['POST', '/v1/sso_onboarding_profiles/prof_1/tickets', ['return_url' => 'https://app.example.com/done'], static fn (Client $c) => $c->ssoOnboarding->createTicket('prof_1', ['return_url' => 'https://app.example.com/done'])],
            ['POST', '/v1/webhook_endpoints', ['url' => 'https://hooks.example.com/atlas', 'active' => false, 'secret' => 'whsec_' . str_repeat('a', 32)], static fn (Client $c) => $c->webhooks->endpoints->create(['url' => 'https://hooks.example.com/atlas', 'active' => false, 'secret' => 'whsec_' . str_repeat('a', 32)])],
            ['POST', '/v1/webhook_endpoints/we_1/rotate_secret', ['secret' => 'whsec_' . str_repeat('b', 32)], static fn (Client $c) => $c->webhooks->endpoints->rotateSecret('we_1', ['secret' => 'whsec_' . str_repeat('b', 32)])],
            ['POST', '/v1/users', ['email_address' => 'a@example.com', 'password_hash' => 'c2FsdA==$aGFzaA==', 'password_hash_algorithm' => 'scrypt', 'password_hash_params' => ['N' => 16384, 'r' => 8, 'p' => 1]],
                static fn (Client $c) => $c->users->create(['email_address' => 'a@example.com', 'password_hash' => 'c2FsdA==$aGFzaA==', 'password_hash_algorithm' => 'scrypt', 'password_hash_params' => ['N' => 16384, 'r' => 8, 'p' => 1]])],
            ['POST', '/v1/platform/apps/app_1/initial_secret/reveal', null, static fn (Client $c) => $c->platform->apps->revealInitialSecret('app_1')],
            ['POST', '/v1/platform/apps/app_1/initial_secret/reveal?dry_run=true', null, static fn (Client $c) => $c->platform->apps->revealInitialSecret('app_1', true)],
            ['PATCH', '/v1/instance/referrals', ['report_linked_atlas_id_signups' => true, 'partner_portal_url' => 'https://partners.example.com'], static fn (Client $c) => $c->referrals->update(['report_linked_atlas_id_signups' => true, 'partner_portal_url' => 'https://partners.example.com'])],
            ['POST', '/v1/domains', ['method' => 'path', 'hostname' => 'app.example.com', 'base_path' => '/__atlas'], static fn (Client $c) => $c->domains->create(['method' => 'path', 'hostname' => 'app.example.com', 'base_path' => '/__atlas'])],
            ['PUT', '/v1/instance/cookie_consent', ['host_aliases' => ['acme-fapi.example.net']], static fn (Client $c) => $c->instance->setCookieConsent(['host_aliases' => ['acme-fapi.example.net']])],
        ];
    }

    public function testEveryCallHitsItsRoute(): void
    {
        foreach (self::calls() as $i => [$verb, $path, $body, $call]) {
            $mock = new MockClient();
            $mock->push(200, ['ok' => true]);
            $call(new Client('sk_test_123', 'https://api.atlas.test', $mock));

            $request = $mock->lastRequest();
            $this->assertSame($verb, $request->getMethod(), "#{$i}");
            $this->assertSame('https://api.atlas.test' . $path, (string) $request->getUri(), "#{$i}");
            $sent = (string) $request->getBody();
            if ($body === null) {
                $this->assertSame('', $sent, "#{$i} should send no body");
            } elseif ($body === []) {
                $this->assertSame('{}', $sent, "#{$i}");
            } else {
                $this->assertSame($body, json_decode($sent, true), "#{$i} body");
            }
        }
    }

    public function testQueryKeepsListsSpreadAndKeysBracketed(): void
    {
        $this->assertSame('?a=1&a=2', Http::serializeQuery(['a' => [1, 2]]));
        $this->assertSame('?m%5Bk%5D=v', Http::serializeQuery(['m' => ['k' => 'v', 'skip' => null]]));
    }

    public function testNewWebhookEventNames(): void
    {
        $this->assertSame('sso_connection.activated', WebhookEvents::SSO_CONNECTION_ACTIVATED);
        $this->assertSame('sso_onboarding_ticket.completed', WebhookEvents::SSO_ONBOARDING_TICKET_COMPLETED);
        $this->assertSame('scim_group.member_removed', WebhookEvents::SCIM_GROUP_MEMBER_REMOVED);
        $this->assertFalse(WebhookEvents::isDeprecated(WebhookEvents::SCIM_GROUP_CREATED));
    }

    public function testPreviousIssuerIsAcceptedAndOthersAreNot(): void
    {
        $keys = new Keys();
        $now = 1_700_000_000;
        $mint = static fn (string $iss): string => JWT::encode(
            ['iss' => $iss, 'sub' => 'user_1', 'sid' => 'sess_1', 'iat' => $now - 5, 'nbf' => $now - 5, 'exp' => $now + 60],
            $keys->privateKeyPem,
            'RS256',
            $keys->kid,
        );
        $verifier = static function (array $previous) use ($keys, $now): SessionVerifier {
            $mock = new MockClient();
            $mock->push(200, $keys->jwks);

            return new SessionVerifier(
                jwksUrl: 'https://app.example.com/__atlas/.well-known/jwks.json',
                issuer: 'https://app.example.com/__atlas',
                httpClient: $mock,
                requestFactory: new HttpFactory(),
                streamFactory: new HttpFactory(),
                now: static fn (): int => $now,
                previousIssuers: $previous,
            );
        };

        $this->assertTrue($verifier(['https://acme-fapi.example.net'])->verify($mint('https://acme-fapi.example.net'))->ok);
        $this->assertTrue($verifier(['https://acme-fapi.example.net'])->verify($mint('https://app.example.com/__atlas'))->ok);
        $this->assertSame('invalid', $verifier(['https://acme-fapi.example.net'])->verify($mint('https://other.example.net'))->reason);
        $this->assertSame('invalid', $verifier([])->verify($mint('https://acme-fapi.example.net'))->reason);
    }
}
