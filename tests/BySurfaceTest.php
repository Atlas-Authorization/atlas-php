<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\BotId;
use Atlas\Client;
use Atlas\Constants;
use Atlas\DeviceAuthorizationException;
use Atlas\DeviceAuthorizations;
use Atlas\Exception\ConfigException;
use Atlas\Tests\Support\MockClient;
use Atlas\Verify\AccessTokenVerifier;
use Atlas\Verify\Introspector;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Billing add-ons / settings / checks, custom OAuth providers, the Actions gallery,
 * session custom data, sign-in settings, SAML encryption keys, dormant accounts, token
 * formats / lifetimes, KMS signing keys, opaque-token introspection, device approval
 * and BotID attestations.
 */
final class BySurfaceTest extends TestCase
{
    /**
     * @return list<array{0:string,1:string,2:array<string,mixed>|null,3:\Closure(Client):mixed}>
     */
    private static function operations(): array
    {
        return [
            ['GET', '/v1/billing/subscriptions/bsub_1', null, static fn (Client $c) => $c->billing->getSubscription('bsub_1')],
            ['POST', '/v1/billing/plans/bp_1/sync_entitlements', null, static fn (Client $c) => $c->billing->syncPlanEntitlements('bp_1')],
            ['GET', '/v1/billing/settings', null, static fn (Client $c) => $c->billing->getSettings()],
            ['PUT', '/v1/billing/settings', ['default_limit_merge' => 'sum'], static fn (Client $c) => $c->billing->updateSettings(['default_limit_merge' => 'sum'])],
            ['POST', '/v1/billing/check', ['subject_type' => 'org', 'subject_id' => 'org_1', 'feature' => 'api'], static fn (Client $c) => $c->billing->checkFeature(['subject_type' => 'org', 'subject_id' => 'org_1', 'feature' => 'api'])],
            ['PATCH', '/v1/billing/plans/bp_1', ['kind' => 'addon', 'seat_feature_id' => null], static fn (Client $c) => $c->billing->updatePlan('bp_1', ['kind' => 'addon', 'seat_feature_id' => null])],
            ['GET', '/v1/custom_oauth_providers', null, static fn (Client $c) => $c->customOAuthProviders->list()],
            ['POST', '/v1/custom_oauth_providers', ['provider_key' => 'custom_acme', 'display_name' => 'Acme'], static fn (Client $c) => $c->customOAuthProviders->create(['provider_key' => 'custom_acme', 'display_name' => 'Acme'])],
            ['GET', '/v1/custom_oauth_providers/coap_1', null, static fn (Client $c) => $c->customOAuthProviders->get('coap_1')],
            ['PATCH', '/v1/custom_oauth_providers/coap_1', ['icon_url' => null], static fn (Client $c) => $c->customOAuthProviders->update('coap_1', ['icon_url' => null])],
            ['DELETE', '/v1/custom_oauth_providers/coap_1', null, static fn (Client $c) => $c->customOAuthProviders->delete('coap_1')],
            ['POST', '/v1/custom_oauth_providers/discover', ['issuer' => 'https://idp.test'], static fn (Client $c) => $c->customOAuthProviders->discover('https://idp.test')],
            ['POST', '/v1/custom_oauth_providers/profile_mapping/preview', ['userinfo' => ['mail' => 'a']], static fn (Client $c) => $c->customOAuthProviders->previewProfileMapping(['userinfo' => ['mail' => 'a']])],
            ['POST', '/v1/custom_oauth_providers/coap_1/profile_mapping/preview', ['id_token' => 'a.b.c'], static fn (Client $c) => $c->customOAuthProviders->previewProfileMapping(['id_token' => 'a.b.c'], 'coap_1')],
            ['GET', '/v1/custom_oauth_providers/coap_1/signing_keys', null, static fn (Client $c) => $c->customOAuthProviders->listSigningKeys('coap_1')],
            ['POST', '/v1/custom_oauth_providers/coap_1/signing_keys', ['alg' => 'PS256'], static fn (Client $c) => $c->customOAuthProviders->rotateSigningKey('coap_1', 'PS256')],
            ['POST', '/v1/custom_oauth_providers/coap_1/signing_keys/k1/retire', null, static fn (Client $c) => $c->customOAuthProviders->retireSigningKey('coap_1', 'k1')],
            ['POST', '/v1/sso_connections/sso_1/profile_mapping/preview', ['userinfo' => ['a' => 1]], static fn (Client $c) => $c->ssoConnections->previewProfileMapping('sso_1', ['userinfo' => ['a' => 1]])],
            ['GET', '/v1/sso_connections/sso_1/signing_keys', null, static fn (Client $c) => $c->ssoConnections->listSigningKeys('sso_1')],
            ['POST', '/v1/sso_connections/sso_1/signing_keys', [], static fn (Client $c) => $c->ssoConnections->rotateSigningKey('sso_1')],
            ['POST', '/v1/sso_connections/sso_1/signing_keys/k2/retire', null, static fn (Client $c) => $c->ssoConnections->retireSigningKey('sso_1', 'k2')],
            ['GET', '/v1/sso_connections/sso_1/saml_encryption_key', null, static fn (Client $c) => $c->ssoConnections->getSamlEncryptionKey('sso_1')],
            ['POST', '/v1/sso_connections/sso_1/saml_encryption_key', null, static fn (Client $c) => $c->ssoConnections->createSamlEncryptionKey('sso_1')],
            ['DELETE', '/v1/sso_connections/sso_1/saml_encryption_key/previous', null, static fn (Client $c) => $c->ssoConnections->retirePreviousSamlEncryptionKey('sso_1')],
            ['DELETE', '/v1/sso_connections/sso_1/saml_encryption_key', null, static fn (Client $c) => $c->ssoConnections->deleteSamlEncryptionKey('sso_1')],
            ['GET', '/v1/action_gallery?q=geo', null, static fn (Client $c) => $c->actionGallery->list(['q' => 'geo'])],
            ['GET', '/v1/action_gallery/geo-fence', null, static fn (Client $c) => $c->actionGallery->get('geo-fence')],
            ['POST', '/v1/action_gallery/geo-fence/install', ['secrets' => ['K' => 'v']], static fn (Client $c) => $c->actionGallery->install('geo-fence', ['secrets' => ['K' => 'v']])],
            ['GET', '/v1/action_gallery_installs', null, static fn (Client $c) => $c->actionGallery->installs()],
            ['POST', '/v1/actions/act_1/gallery_update', [], static fn (Client $c) => $c->actionGallery->update('act_1')],
            ['GET', '/v1/action_templates', null, static fn (Client $c) => $c->actionTemplates->list()],
            ['POST', '/v1/action_templates', ['slug' => 'mine'], static fn (Client $c) => $c->actionTemplates->create(['slug' => 'mine'])],
            ['GET', '/v1/action_templates/atpl_1', null, static fn (Client $c) => $c->actionTemplates->get('atpl_1')],
            ['PATCH', '/v1/action_templates/atpl_1', ['summary' => 's'], static fn (Client $c) => $c->actionTemplates->update('atpl_1', ['summary' => 's'])],
            ['DELETE', '/v1/action_templates/atpl_1', null, static fn (Client $c) => $c->actionTemplates->delete('atpl_1')],
            ['POST', '/v1/action_templates/atpl_1/versions', ['version' => '1.0.0', 'code' => 'x'], static fn (Client $c) => $c->actionTemplates->addVersion('atpl_1', ['version' => '1.0.0', 'code' => 'x'])],
            ['POST', '/v1/action_templates/atpl_1/versions/1.0.0/submit', null, static fn (Client $c) => $c->actionTemplates->submitVersion('atpl_1', '1.0.0')],
            ['DELETE', '/v1/action_templates/atpl_1/versions/1.0.0', null, static fn (Client $c) => $c->actionTemplates->deleteVersion('atpl_1', '1.0.0')],
            ['POST', '/v1/action_templates/atpl_1/publish', null, static fn (Client $c) => $c->actionTemplates->publish('atpl_1')],
            ['POST', '/v1/action_templates/atpl_1/unlist', null, static fn (Client $c) => $c->actionTemplates->unlist('atpl_1')],
            ['PUT', '/v1/sessions/sess_1/custom_data', ['custom_data' => null], static fn (Client $c) => $c->sessions->setCustomData('sess_1', null)],
            ['PATCH', '/v1/sessions/sess_1/custom_data', ['custom_data' => ['plan' => null, 'seat' => 2]], static fn (Client $c) => $c->sessions->updateCustomData('sess_1', ['plan' => null, 'seat' => 2])],
            ['PUT', '/v1/instance/wallet_rpc_secret', ['chain_id' => 1, 'url' => 'https://rpc.test'], static fn (Client $c) => $c->instanceSecurity->setWalletRpcSecret(1, 'https://rpc.test')],
            ['PATCH', '/v1/instance', ['auth_config' => ['accountLinkingTrust' => ['google' => 'always']]], static fn (Client $c) => $c->instance->setAccountLinkingTrust(['google' => 'always'])],
            ['PATCH', '/v1/instance', ['auth_config' => ['captcha' => ['provider' => 'vercel_botid', 'attestationUrl' => '/api/botid']]], static fn (Client $c) => $c->instance->setCaptchaConfig(['provider' => 'vercel_botid', 'attestationUrl' => '/api/botid'])],
            ['GET', '/v1/instance/signing_key_provider', null, static fn (Client $c) => $c->instanceSecurity->getSigningKeyProvider()],
            ['PUT', '/v1/instance/signing_key_provider', ['provider' => 'aws_kms', 'config' => ['region' => 'us-east-1'], 'credentials' => ['access_key_id' => 'a']], static fn (Client $c) => $c->instanceSecurity->setSigningKeyProvider(['provider' => 'aws_kms', 'config' => ['region' => 'us-east-1'], 'credentials' => ['access_key_id' => 'a']])],
            ['DELETE', '/v1/instance/signing_key_provider', ['emergency' => true], static fn (Client $c) => $c->instanceSecurity->removeSigningKeyProvider(['emergency' => true])],
            ['PATCH', '/v1/rate_limit_policy', ['rules' => [['group' => 'fapi.sign_in', 'window_seconds' => 60, 'max' => 5, 'key' => 'ip']]], static fn (Client $c) => $c->rateLimitPolicy->update(['rules' => [['group' => 'fapi.sign_in', 'window_seconds' => 60, 'max' => 5, 'key' => 'ip']]])],
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

    public function testConstants(): void
    {
        $this->assertContains('captchafox', Constants::CAPTCHA_PROVIDERS);
        $this->assertContains('vercel_botid', Constants::CAPTCHA_PROVIDERS);
        $this->assertContains('commet', Constants::BILLING_PROVIDERS);
        $this->assertSame('session.updated', Constants::WEBHOOK_EVENT_SESSION_UPDATED);
        $this->assertSame('user.reactivated', Constants::WEBHOOK_EVENT_USER_REACTIVATED);
    }

    public function testBotIdAttestation(): void
    {
        $secret = str_repeat('k', 32);
        $tok = BotId::createAttestation($secret, false, ttlSeconds: 3600, now: 1_800_000_000);
        $this->assertStringStartsWith(BotId::PREFIX, $tok);
        [$payload, $sig] = explode('.', substr($tok, strlen(BotId::PREFIX)));
        $expect = rtrim(strtr(base64_encode(hash_hmac('sha256', BotId::PREFIX . $payload, $secret, true)), '+/', '-_'), '=');
        $this->assertSame($expect, $sig);
        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $this->assertSame(1, $data['v']);
        $this->assertFalse($data['is_bot']);
        $this->assertSame(1_800_000_000, $data['iat']);
        $this->assertSame(300, $data['exp'] - $data['iat']);
        $this->assertGreaterThanOrEqual(20, strlen($data['jti']));
        $this->expectException(ConfigException::class);
        BotId::createAttestation('short', false);
    }

    public function testDeviceAuthorizations(): void
    {
        $mock = new MockClient();
        $ok = ['object' => 'device_authorization', 'id' => 'da_1', 'user_code' => 'WDJB-MJHT', 'client' => ['name' => 'TV'], 'requires_step_up' => true];
        $mock->push(200, $ok);
        $mock->push(200, $ok);
        $mock->push(200, $ok);
        $mock->push(403, ['errors' => [['code' => 'STEP_UP_REQUIRED', 'message' => 'Re-authenticate.']]]);
        $d = new DeviceAuthorizations('https://fapi.test/', 'sess_jwt', 'pk_x', $mock);
        $auth = $d->lookup(' wdjb mjht ');
        $req = $mock->lastRequest();
        $this->assertSame('https://fapi.test/v1/client/me/device_authorizations/lookup', (string) $req->getUri());
        $this->assertSame('Bearer sess_jwt', $req->getHeaderLine('Authorization'));
        $this->assertSame('pk_x', $req->getHeaderLine('x-publishable-key'));
        $this->assertSame(['user_code' => 'WDJB-MJHT'], json_decode((string) $req->getBody(), true));
        $this->assertSame('TV', $auth['client']['name']);
        $d->approve($auth);
        $this->assertSame('https://fapi.test/v1/client/me/device_authorizations/da_1/approve', (string) $mock->lastRequest()->getUri());
        $d->deny($auth);
        $this->assertSame('https://fapi.test/v1/client/me/device_authorizations/da_1/deny', (string) $mock->lastRequest()->getUri());
        try {
            $d->approve($auth);
            $this->fail('expected a refusal');
        } catch (DeviceAuthorizationException $e) {
            $this->assertTrue($e->needsReverification());
            $this->assertFalse($e->isInvalidCode());
            $this->assertSame(403, $e->status);
        }
        $this->assertSame('abc', DeviceAuthorizations::formatUserCode('abc'));
        $this->assertSame('WDJB-MJHT', DeviceAuthorizations::userCodeFromUrl('https://x.test/d?user_code=wdjbmjht'));
        $this->assertNull(DeviceAuthorizations::userCodeFromUrl('nope'));
    }

    private const ISSUER = 'https://issuer.test';
    private const AUD = 'https://api.test';

    /**
     * A client answering introspection calls; `$state['active']` turns every token off.
     *
     * @param array{active:bool,calls:int} $state
     */
    private static function introspectionClient(array &$state): \Psr\Http\Client\ClientInterface
    {
        return new class ($state) implements \Psr\Http\Client\ClientInterface {
            /** @var list<RequestInterface> */
            public array $requests = [];

            /** @param array{active:bool,calls:int} $state */
            public function __construct(private array &$state)
            {
            }

            public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $this->requests[] = $request;
                ++$this->state['calls'];
                parse_str((string) $request->getBody(), $form);
                $token = $form['token'] ?? '';
                if ($token === 'oat_down') {
                    return new Response(500);
                }
                if (!$this->state['active'] || $token === 'oat_dead') {
                    return new Response(200, [], '{"active":false}');
                }
                $iss = $token === 'oat_foreign' ? 'https://other.test' : 'https://issuer.test';

                return new Response(200, [], (string) json_encode(['active' => true, 'iss' => $iss, 'sub' => 'u_1', 'aud' => 'https://api.test', 'scope' => 'read', 'client_id' => 'oc_1', 'exp' => time() + 3600]));
            }
        };
    }

    public function testAccessTokenIntrospection(): void
    {
        $state = ['active' => true, 'calls' => 0];
        $client = self::introspectionClient($state);
        $v = new AccessTokenVerifier(self::ISSUER, self::AUD, httpClient: $client, introspection: ['clientId' => 'oc_api', 'clientSecret' => 's3cret']);
        $ok = $v->verify('Bearer oat_live', ['read']);
        $this->assertTrue($ok->ok, $ok->description);
        $this->assertSame('u_1', $ok->subject);
        $req = $client->requests[0];
        $this->assertSame('https://issuer.test/oauth2/introspect', (string) $req->getUri());
        $this->assertSame('Basic ' . base64_encode('oc_api:s3cret'), $req->getHeaderLine('Authorization'));
        parse_str((string) $req->getBody(), $form);
        $this->assertSame('access_token', $form['token_type_hint']);
        $v->verify('Bearer oat_live');
        $this->assertSame(1, $state['calls'], 'cached');
        $this->assertSame(403, $v->verify('Bearer oat_live', ['admin'])->status);
        $this->assertFalse($v->verify('Bearer oat_dead')->ok);
        $n = $state['calls'];
        $v->verify('Bearer oat_dead');
        $this->assertSame($n, $state['calls'], 'negative answer cached');
        $foreign = $v->verify('Bearer oat_foreign');
        $this->assertFalse($foreign->ok);
        $this->assertStringContainsString('another authorization server', $foreign->description);
        $down = $v->verify('Bearer oat_down');
        $this->assertFalse($down->ok);
        $this->assertStringContainsString('could not be validated', $down->description);

        $plain = new AccessTokenVerifier(self::ISSUER, self::AUD, httpClient: new MockClient());
        $refused = $plain->verify('Bearer oat_live');
        $this->assertFalse($refused->ok);
        $this->assertStringContainsString('introspection', $refused->description);

        // introspectJwts: a JWT is checked live too (here: revoked).
        $state['active'] = false;
        $live = new AccessTokenVerifier(self::ISSUER, self::AUD, httpClient: $client, introspection: ['clientId' => 'oc_api', 'clientSecret' => 's3cret', 'introspectJwts' => true]);
        $this->assertFalse($live->verify('Bearer aaa.bbb.ccc')->ok);

        $this->expectException(ConfigException::class);
        new Introspector(self::ISSUER, '', '');
    }

    public function testIntrospectorCacheBoundsAndExpiry(): void
    {
        $clock = 1_800_000_000;
        $hits = 0;
        $client = new class ($hits) implements \Psr\Http\Client\ClientInterface {
            public function __construct(private int &$hits)
            {
            }

            public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                ++$this->hits;

                return new Response(200, [], '{"active":true,"exp":1800000005}');
            }
        };
        $i = new Introspector(self::ISSUER, 'a', 'b', cacheMax: 2, httpClient: $client, now: static function () use (&$clock): int {
            return $clock;
        });
        foreach (['t1', 't2', 't3'] as $t) {
            $this->assertNotNull($i->introspect($t));
        }
        $this->assertSame(2, $i->cacheSize());
        $i->introspect('t3');
        $n = $hits;
        $clock += 6;
        $i->introspect('t3');
        $this->assertSame($n + 1, $hits, 'never reused past exp');
    }
}
