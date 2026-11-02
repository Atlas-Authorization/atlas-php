<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\Tests\Support\MockClient;
use PHPUnit\Framework\TestCase;

/**
 * The 0.23.0 surface: oauthClients->logout, refresh_touches_session / add_redirect_origins on
 * client writes and the captcha secret's site key.
 */
final class OAuthClientLogoutTest extends TestCase
{
    /**
     * @return list<array{0:string,1:string,2:array<string,mixed>,3:\Closure(Client):mixed}>
     */
    private static function calls(): array
    {
        return [
            ['POST', '/v1/oauth_clients/oc_1/logout', ['sid' => 'sess_1'], static fn (Client $c) => $c->oauthClients->logout('oc_1', 'sess_1')],
            ['POST', '/v1/oauth_clients', ['name' => 'App', 'redirect_uris' => ['https://app.example.com/cb'], 'first_party' => true, 'refresh_touches_session' => true, 'add_redirect_origins' => true],
                static fn (Client $c) => $c->oauthClients->create(['name' => 'App', 'redirect_uris' => ['https://app.example.com/cb'], 'first_party' => true, 'refresh_touches_session' => true, 'add_redirect_origins' => true])],
            ['PATCH', '/v1/oauth_clients/oc_1', ['refresh_touches_session' => false, 'add_redirect_origins' => true],
                static fn (Client $c) => $c->oauthClients->update('oc_1', ['refresh_touches_session' => false, 'add_redirect_origins' => true])],
            ['PUT', '/v1/instance/captcha_secret', ['secret' => '0x4AAA', 'provider' => 'turnstile', 'site_key' => '0x4BBB'],
                static fn (Client $c) => $c->instanceSecurity->setCaptchaSecret('0x4AAA', 'turnstile', '0x4BBB')],
            ['PUT', '/v1/instance/captcha_secret', ['secret' => '0x4AAA'], static fn (Client $c) => $c->instanceSecurity->setCaptchaSecret('0x4AAA')],
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
            $this->assertSame($body, json_decode((string) $request->getBody(), true), "#{$i} body");
        }
    }

    public function testLogoutReturnsTheResultAndSendsTheIdempotencyKey(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['object' => 'oauth_client_logout', 'client_id' => 'atlas_client_1', 'sid' => 'sess_1', 'session' => 'revoked', 'grants_revoked' => 2, 'backchannel_logouts' => 3]);
        $out = (new Client('sk_test_123', 'https://api.atlas.test', $mock))->oauthClients->logout('oc_1', 'sess_1', 'idem_1');

        $this->assertSame('idem_1', $mock->lastRequest()->getHeaderLine('Idempotency-Key'));
        $this->assertSame('revoked', $out['session']);
        $this->assertSame(3, $out['backchannel_logouts']);
    }
}
