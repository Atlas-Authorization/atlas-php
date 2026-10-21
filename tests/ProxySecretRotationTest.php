<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\Tests\Support\MockClient;
use PHPUnit\Framework\TestCase;

/**
 * The trusted reverse proxy secret, and secret rotation with an overlap for webhook endpoints,
 * per-instance platform keys and Sign in with Atlas apps.
 */
final class ProxySecretRotationTest extends TestCase
{
    private const BASE = 'https://api.atlas.test';
    private const VIEW = [
        'object' => 'proxy_secret', 'set' => true, 'created_at' => 1800000000000, 'previous_expires_at' => null,
        'secret_header' => 'X-Atlas-Proxy-Secret', 'client_ip_header' => 'X-Atlas-Client-IP',
    ];

    private MockClient $mock;
    private Client $atlas;
    private Client $platformClient;

    protected function setUp(): void
    {
        $this->mock = new MockClient();
        $this->atlas = new Client('sk_test_secret', self::BASE, $this->mock);
        $this->platformClient = new Client('psk_test_1', self::BASE, $this->mock);
    }

    private function body(): mixed
    {
        return json_decode((string) $this->mock->lastRequest()->getBody(), true);
    }

    public function testProxySecretGetCreateRotateDelete(): void
    {
        $url = self::BASE . '/v1/instance/proxy_secret';

        $this->mock->push(200, self::VIEW);
        $this->assertSame('X-Atlas-Client-IP', $this->atlas->instanceSecurity->getProxySecret()['client_ip_header']);
        $this->assertSame('GET', $this->mock->lastRequest()->getMethod());
        $this->assertSame($url, (string) $this->mock->lastRequest()->getUri());

        $this->mock->push(201, self::VIEW + ['secret' => 'aps_1', 'rotated' => false]);
        $this->assertSame('aps_1', $this->atlas->instanceSecurity->createProxySecret()['secret']);
        $this->assertSame('POST', $this->mock->lastRequest()->getMethod());
        $this->assertSame('', (string) $this->mock->lastRequest()->getBody());

        $this->mock->push(200, ['secret' => 'aps_2', 'rotated' => true, 'previous_expires_at' => 1800003600000] + self::VIEW);
        $out = $this->atlas->instanceSecurity->createProxySecret(['overlap_seconds' => 3600]);
        $this->assertSame(['overlap_seconds' => 3600], $this->body());
        $this->assertTrue($out['rotated']);
        $this->assertSame(1800003600000, $out['previous_expires_at']);

        $this->mock->push(200, ['object' => 'proxy_secret', 'deleted' => true]);
        $this->assertTrue($this->atlas->instanceSecurity->deleteProxySecret()['deleted']);
        $this->assertSame('DELETE', $this->mock->lastRequest()->getMethod());
        $this->assertSame($url, (string) $this->mock->lastRequest()->getUri());
    }

    public function testWebhookRotateSecretWithOverlap(): void
    {
        $this->mock->push(200, [
            'object' => 'webhook_secret', 'secret' => 'whsec_new', 'overlap_seconds' => 600,
            'previous_secret_expires_at' => 1800000600000,
        ]);
        $out = $this->atlas->webhooks->endpoints->rotateSecret('whep_1', ['overlap_seconds' => 600]);
        $this->assertSame(self::BASE . '/v1/webhook_endpoints/whep_1/rotate_secret', (string) $this->mock->lastRequest()->getUri());
        $this->assertSame(['overlap_seconds' => 600], $this->body());
        $this->assertSame(1800000600000, $out['previous_secret_expires_at']);

        $this->mock->push(200, ['object' => 'webhook_secret', 'secret' => 'whsec_2']);
        $this->atlas->webhooks->endpoints->rotateSecret('whep_1');
        $this->assertSame('', (string) $this->mock->lastRequest()->getBody());
    }

    public function testPlatformInstanceRotationWithOverlapAndTheLegacySignature(): void
    {
        $url = self::BASE . '/v1/platform/apps/app_1/instances/ins_1/rotate_secret';
        $this->mock->push(200, [
            'object' => 'app_secret', 'app_id' => 'app_1', 'instance_id' => 'ins_1', 'secret' => 'sk_x',
            'overlap_seconds' => 0, 'previous_keys_expire_at' => null,
        ]);
        $out = $this->platformClient->platform->apps->rotateInstanceSecret('app_1', 'ins_1', ['overlap_seconds' => 0], 'idem_1');
        $request = $this->mock->lastRequest();
        $this->assertSame($url, (string) $request->getUri());
        $this->assertSame(['overlap_seconds' => 0], $this->body());
        $this->assertSame('idem_1', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame('ins_1', $out['instance_id']);

        // The 0.7 call: the idempotency key as the third argument, no body.
        $this->mock->push(200, ['object' => 'app_secret', 'app_id' => 'app_1']);
        $this->platformClient->platform->apps->rotateInstanceSecret('app_1', 'ins_1', 'idem_2');
        $request = $this->mock->lastRequest();
        $this->assertSame('', (string) $request->getBody());
        $this->assertSame('idem_2', $request->getHeaderLine('Idempotency-Key'));
    }

    public function testAtlasIdAppRotationsWithOverlap(): void
    {
        $apps = $this->platformClient->platform->atlasIdApps;
        $this->mock->push(200, [
            'object' => 'atlas_id_app', 'id' => 'aia_1', 'client_secret' => 'cs_new',
            'previous_secret_expires_at' => 1800003600000, 'secret_issued_at' => 1800000000000,
        ]);
        $out = $apps->rotateSecret('aia_1', ['overlap_seconds' => 3600]);
        $this->assertSame(self::BASE . '/v1/platform/atlas_id_apps/aia_1/rotate_secret', (string) $this->mock->lastRequest()->getUri());
        $this->assertSame(['overlap_seconds' => 3600], $this->body());
        $this->assertSame(1800000000000, $out['secret_issued_at']);

        $this->mock->push(200, [
            'object' => 'atlas_id_app', 'id' => 'aia_1', 'webhook_secret' => 'whsec_new',
            'webhook_previous_secret_expires_at' => 1800000600000,
        ]);
        $hook = $apps->rotateWebhookSecret('aia_1', ['overlap_seconds' => 600]);
        $this->assertSame(self::BASE . '/v1/platform/atlas_id_apps/aia_1/webhook/rotate_secret', (string) $this->mock->lastRequest()->getUri());
        $this->assertSame(['overlap_seconds' => 600], $this->body());
        $this->assertSame(1800000600000, $hook['webhook_previous_secret_expires_at']);

        $this->mock->push(200, ['object' => 'atlas_id_app', 'id' => 'aia_1']);
        $apps->rotateWebhookSecret('aia_1');
        $this->assertSame('', (string) $this->mock->lastRequest()->getBody());
    }
}
