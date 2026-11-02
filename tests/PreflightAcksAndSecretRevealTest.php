<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\Tests\Support\MockClient;
use Atlas\WebhookEvents;
use PHPUnit\Framework\TestCase;

/**
 * The 0.23.0 second round: preflight acknowledgements, the OAuth client secret reveal and
 * caller-chosen secret, require_verified_email, invitation expiry, platform app filters,
 * the before_user_create fail_closed switch and the new event names.
 */
final class PreflightAcksAndSecretRevealTest extends TestCase
{
    /**
     * @return list<array{0:string,1:string,2:array<string,mixed>|null,3:\Closure(Client):mixed}>
     */
    private static function calls(): array
    {
        return [
            ['GET', '/v1/instance/preflight/acks', null, static fn (Client $c) => $c->instance->listPreflightAcks()],
            ['PUT', '/v1/instance/preflight/acks/webhook_endpoint_off_origin', ['reason' => 'Relay host', 'expires_at' => 1900000000000],
                static fn (Client $c) => $c->instance->setPreflightAck('webhook_endpoint_off_origin', ['reason' => 'Relay host', 'expires_at' => 1900000000000])],
            ['DELETE', '/v1/instance/preflight/acks/a%20b', null, static fn (Client $c) => $c->instance->deletePreflightAck('a b')],
            ['POST', '/v1/oauth_clients/oc_1/secret/reveal', null, static fn (Client $c) => $c->oauthClients->revealSecret('oc_1')],
            ['POST', '/v1/oauth_clients', ['name' => 'App', 'redirect_uris' => ['https://app.example.com/cb'], 'client_secret' => str_repeat('s', 40), 'require_verified_email' => true],
                static fn (Client $c) => $c->oauthClients->create(['name' => 'App', 'redirect_uris' => ['https://app.example.com/cb'], 'client_secret' => str_repeat('s', 40), 'require_verified_email' => true])],
            ['PATCH', '/v1/oauth_clients/oc_1', ['require_verified_email' => false],
                static fn (Client $c) => $c->oauthClients->update('oc_1', ['require_verified_email' => false])],
            ['POST', '/v1/invitations', ['email_address' => 'a@example.com', 'expires_in_days' => 30],
                static fn (Client $c) => $c->invitations->create(['email_address' => 'a@example.com', 'expires_in_days' => 30])],
            ['GET', '/v1/platform/apps?name_prefix=acme&external_ref=acme%2Fprod&limit=10', null,
                static fn (Client $c) => $c->platform->apps->list(['name_prefix' => 'acme', 'external_ref' => 'acme/prod', 'limit' => 10])],
            ['PATCH', '/v1/instance/hooks/before_user_create', ['fail_closed' => true],
                static fn (Client $c) => $c->userHooks->updateBeforeUserCreate(['fail_closed' => true])],
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

    public function testRevealReturnsTheSecret(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['object' => 'oauth_client_secret', 'id' => 'oc_1', 'client_id' => 'atlas_client_1', 'client_secret' => 'sec_1', 'secret_prefix' => null, 'client_secret_expires_at' => null, 'note' => 'n']);
        $out = (new Client('sk_test_123', 'https://api.atlas.test', $mock))->oauthClients->revealSecret('oc_1');

        $this->assertSame('sec_1', $out['client_secret']);
    }

    public function testNewEventNames(): void
    {
        $this->assertSame('session.expired', WebhookEvents::SESSION_EXPIRED);
        $this->assertSame('invitation.created', WebhookEvents::INVITATION_CREATED);
        $this->assertSame('invitation.accepted', WebhookEvents::INVITATION_ACCEPTED);
        $this->assertSame('invitation.revoked', WebhookEvents::INVITATION_REVOKED);
        $this->assertSame('session.expired', WebhookEvents::canonical('session.expired'));
    }
}
