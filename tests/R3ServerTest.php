<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\ErrorCode;
use Atlas\Tests\Support\MockClient;
use PHPUnit\Framework\TestCase;

/** Round-3 server surface: partner platforms, per-call audience, orchestrator fields, include_deleted, new error code. */
final class R3ServerTest extends TestCase
{
    private const BASE = 'https://api.atlas.test';
    private const TOKEN = 'SECRET-TOKEN-VALUE';

    public function testPartnerPlatformsWireSurface(): void
    {
        $mock = new MockClient();
        for ($i = 0; $i < 7; $i++) {
            $mock->push(200, ['object' => 'partner_platform', 'partner' => 'acme', 'token_set' => true, 'token_prefix' => 'tok_ab']);
        }
        $c = new Client('sk_test_1', self::BASE, $mock);

        $c->partnerPlatforms->list();
        $c->partnerPlatforms->get('acme');
        $c->partnerPlatforms->set('acme', ['enabled' => true]);
        $c->partnerPlatforms->remove('acme');
        $c->partnerPlatforms->registration('acme');
        $c->partnerPlatforms->deliveries('acme', 5);
        $c->partnerPlatforms->storeToken('acme', self::TOKEN, true, 'https://api.example.com');

        $expected = [
            ['GET', '/v1/partner_platforms'],
            ['GET', '/v1/partner_platforms/acme'],
            ['PUT', '/v1/partner_platforms/acme'],
            ['DELETE', '/v1/partner_platforms/acme'],
            ['GET', '/v1/partner_platforms/acme/registration'],
            ['GET', '/v1/partner_platforms/acme/deliveries?limit=5'],
            ['POST', '/v1/partner_platforms/acme/token'],
        ];
        foreach ($expected as $i => [$method, $path]) {
            $this->assertSame($method, $mock->requests[$i]->getMethod(), $path);
            $this->assertSame(self::BASE . $path, (string) $mock->requests[$i]->getUri());
        }
        $this->assertSame(['enabled' => true], json_decode((string) $mock->requests[2]->getBody(), true));
        $this->assertSame(
            ['token' => self::TOKEN, 'rotate' => true, 'api_base_url' => 'https://api.example.com'],
            json_decode((string) $mock->requests[6]->getBody(), true),
        );
    }

    public function testPartnerTokenIsNeverInAUrlOrAnswer(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['object' => 'partner_platform', 'partner' => 'acme', 'token_set' => true, 'token_prefix' => 'tok_ab']);
        $c = new Client('sk_test_1', self::BASE, $mock);

        $answer = $c->partnerPlatforms->storeToken('acme', self::TOKEN);

        $this->assertStringNotContainsString(self::TOKEN, (string) $mock->requests[0]->getUri());
        $this->assertStringNotContainsString(self::TOKEN, json_encode($answer, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString(self::TOKEN, var_export($answer, true));
        $this->assertTrue($answer['token_set']);
    }

    public function testPerCallAudienceOnTheIntegrationTokenMint(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['jwt' => 'x']);
        $mock->push(200, ['jwt' => 'x']);
        $mock->push(200, ['jwt' => 'x']);
        $c = new Client('sk_test_1', self::BASE, $mock);

        $c->sessions->getToken('sess_1', ['for' => 'acme', 'cbid' => 'dv-1', 'audience' => 'acme:x', 'audienceParams' => ['tenant' => 't1']]);
        $c->sessions->getToken('sess_1', ['for' => 'acme']);
        $c->sessions->createIntegrationToken('sess_1', 'acme', ['tenant' => 't2']);

        $this->assertSame(self::BASE . '/v1/sessions/sess_1/integration_tokens/acme?tenant=t1&cbid=dv-1&audience=acme%3Ax', (string) $mock->requests[0]->getUri());
        $this->assertSame(self::BASE . '/v1/sessions/sess_1/integration_tokens/acme', (string) $mock->requests[1]->getUri());
        $this->assertSame(self::BASE . '/v1/sessions/sess_1/integration_tokens/acme?tenant=t2', (string) $mock->requests[2]->getUri());
    }

    public function testIntegrationAndOrchestratorBodiesAndUsersFlag(): void
    {
        $mock = new MockClient();
        for ($i = 0; $i < 4; $i++) {
            $mock->push(200, ['ok' => true]);
        }
        $c = new Client('sk_test_1', self::BASE, $mock);

        $c->integrations->set('acme', ['per_call_audience' => ['param' => 'tenant', 'template' => 'acme:{tenant}', 'allowed' => ['t1'], 'any_matching' => false]]);
        $c->integrations->set('acme', ['per_call_audience' => null]);
        $c->privacy->setOrchestrator(['partner_link_id' => 'pl_1', 'site_id' => 's1', 'cancel_deletion_on_sign_in' => true]);
        $c->users->list(['include_deleted' => true]);

        $this->assertSame('acme:{tenant}', json_decode((string) $mock->requests[0]->getBody(), true)['per_call_audience']['template']);
        $this->assertSame('{"per_call_audience":null}', (string) $mock->requests[1]->getBody());
        $this->assertSame(['partner_link_id' => 'pl_1', 'site_id' => 's1', 'cancel_deletion_on_sign_in' => true], json_decode((string) $mock->requests[2]->getBody(), true));
        $this->assertStringContainsString('include_deleted=true', (string) $mock->requests[3]->getUri());
    }

    public function testManagedWafNotConfiguredCode(): void
    {
        $this->assertSame('MANAGED_WAF_NOT_CONFIGURED', ErrorCode::MANAGED_WAF_NOT_CONFIGURED);
    }
}
