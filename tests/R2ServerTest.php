<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\DryRunResult;
use Atlas\Tests\Support\MockClient;
use Atlas\Verify\VerifyResult;
use PHPUnit\Framework\TestCase;

/** Round-2 server surface: adoption, instance app preflight, dry-run result, sealed recipient, SSO claims. */
final class R2ServerTest extends TestCase
{
    private const BASE = 'https://api.atlas.test';

    public function testSetManagedByAndInstancePreflight(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['changed' => true]);
        $mock->push(200, ['changed' => true]);
        $mock->push(200, ['ok' => true]);
        $client = new Client('psk_test_1', self::BASE, $mock);

        $client->platform->atlasIdApps->setManagedBy('app_1', 'ins_1');
        $this->assertSame('PUT', $mock->requests[0]->getMethod());
        $this->assertSame(self::BASE . '/v1/platform/atlas_id_apps/app_1/managed_by', (string) $mock->requests[0]->getUri());
        $this->assertSame(['instance_id' => 'ins_1'], json_decode((string) $mock->requests[0]->getBody(), true));

        $client->platform->atlasIdApps->setManagedBy('app_1', null);
        $this->assertSame('{"instance_id":null}', (string) $mock->requests[1]->getBody());

        $client->atlasIdGrants->preflight('app_1');
        $this->assertSame('GET', $mock->requests[2]->getMethod());
        $this->assertSame(self::BASE . '/v1/atlas_id_apps/app_1/preflight', (string) $mock->requests[2]->getUri());
    }

    public function testSecretRecipientHeader(): void
    {
        $mock = new MockClient();
        $mock->push(200, []);
        $mock->push(200, []);
        $client = new Client('sk_test_1', self::BASE, $mock, secretRecipient: 'x25519:abc');
        $client->whoami->get();
        $this->assertSame('x25519:abc', $mock->requests[0]->getHeaderLine('X-Secret-Recipient'));
        $client->withOptions(dryRun: true)->whoami->get();
        $this->assertSame('x25519:abc', $mock->requests[1]->getHeaderLine('X-Secret-Recipient'));

        $mock2 = new MockClient();
        $mock2->push(200, []);
        (new Client('sk_test_1', self::BASE, $mock2))->withOptions(secretRecipient: 'x25519:zzz')->whoami->get();
        $this->assertSame('x25519:zzz', $mock2->requests[0]->getHeaderLine('X-Secret-Recipient'));
        $mock3 = new MockClient();
        $mock3->push(200, []);
        (new Client('sk_test_1', self::BASE, $mock3))->whoami->get();
        $this->assertSame('', $mock3->requests[0]->getHeaderLine('X-Secret-Recipient'));
    }

    public function testDryRunResult(): void
    {
        $r = DryRunResult::fromArray([
            'mode' => 'rollback', 'validated' => true, 'would_return_status' => 201, 'suppressed' => ['email'],
            'warnings' => [['code' => 'origin_still_referenced']], 'changes' => [],
        ]);
        $this->assertSame('rollback', $r->mode);
        $this->assertTrue($r->validated);
        $this->assertSame(201, $r->wouldReturnStatus);
        $this->assertSame(['email'], $r->suppressed);
        $plan = DryRunResult::fromArray(['mode' => 'plan', 'validated' => false, 'validation_skipped' => ['reason' => 'external_call', 'detail' => 'd']]);
        $this->assertFalse($plan->validated);
        $this->assertSame('external_call', $plan->validationSkipped['reason']);
    }

    public function testSessionSso(): void
    {
        $r = VerifyResult::ok(['sub' => 'u', 'sso_connection_id' => 'sso_1', 'sso_organization_id' => 'org_1', 'sso_connection_type' => 'saml']);
        $this->assertSame(['connection_id' => 'sso_1', 'organization_id' => 'org_1', 'type' => 'saml'], $r->sso());
        $this->assertNull(VerifyResult::ok(['sub' => 'u'])->sso());
        $odd = VerifyResult::ok(['sub' => 'u', 'sso_connection_id' => 's', 'sso_connection_type' => 'ldap'])->sso();
        $this->assertNull($odd['type']);
        $this->assertNull($odd['organization_id']);
    }
}
