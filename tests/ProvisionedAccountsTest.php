<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\ErrorCode;
use Atlas\Tests\Support\MockClient;
use PHPUnit\Framework\TestCase;

/** Test and review accounts, verified domains and the app test-mode fields (0.20.0). */
final class ProvisionedAccountsTest extends TestCase
{
    private const BASE = 'https://api.atlas.test';

    public function testProvisionedAccountRoutes(): void
    {
        $cases = [
            ['GET', '/v1/platform/atlas_id_apps/a_1/provisioned_accounts', null, static fn (Client $c) => $c->platform->atlasIdApps->provisionedAccounts->list('a_1')],
            ['POST', '/v1/platform/atlas_id_apps/a_1/provisioned_accounts', ['email' => 'r@x.test', 'label' => 'rev'], static fn (Client $c) => $c->platform->atlasIdApps->provisionedAccounts->create('a_1', ['email' => 'r@x.test', 'label' => 'rev'], 'idem_1')],
            ['GET', '/v1/platform/atlas_id_apps/a_1/provisioned_accounts/p_1', null, static fn (Client $c) => $c->platform->atlasIdApps->provisionedAccounts->get('a_1', 'p_1')],
            ['DELETE', '/v1/platform/atlas_id_apps/a_1/provisioned_accounts/p_1', null, static fn (Client $c) => $c->platform->atlasIdApps->provisionedAccounts->remove('a_1', 'p_1')],
            ['POST', '/v1/platform/atlas_id_apps/a_1/provisioned_accounts/p_1/password', ['password' => 'a-long-password'], static fn (Client $c) => $c->platform->atlasIdApps->provisionedAccounts->rotatePassword('a_1', 'p_1', ['password' => 'a-long-password'])],
            ['GET', '/v1/platform/domains', null, static fn (Client $c) => $c->platform->domains->list()],
            ['POST', '/v1/platform/domains', ['domain' => 'x.test'], static fn (Client $c) => $c->platform->domains->claim('x.test')],
            ['POST', '/v1/platform/domains/d_1/verify', null, static fn (Client $c) => $c->platform->domains->check('d_1')],
            ['POST', '/v1/platform/domains/d_1/verify', null, static fn (Client $c) => $c->platform->domains->verify('d_1')],
            ['DELETE', '/v1/platform/domains/d_1', null, static fn (Client $c) => $c->platform->domains->remove('d_1')],
            ['PATCH', '/v1/platform/atlas_id_apps/a_1', ['test_mode' => true, 'provisioned_verified_domains_only' => true], static fn (Client $c) => $c->platform->atlasIdApps->update('a_1', ['test_mode' => true, 'provisioned_verified_domains_only' => true])],
        ];
        foreach ($cases as [$verb, $path, $body, $call]) {
            $mock = new MockClient();
            $mock->push(200, ['ok' => true]);
            $call(new Client('psk_1', self::BASE, $mock));
            $request = $mock->lastRequest();
            $this->assertSame($verb, $request->getMethod(), $path);
            $this->assertSame(self::BASE . $path, (string) $request->getUri());
            $sent = (string) $request->getBody();
            $body === null ? $this->assertSame('', $sent) : $this->assertSame($body, json_decode($sent, true));
        }
    }

    public function testSealedPasswordPath(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['object' => 'atlas_id_provisioned_account', 'id' => 'p_1', 'sealed_password' => 'sealed-blob']);
        $mock->push(200, ['object' => 'atlas_id_provisioned_account', 'id' => 'p_1', 'sealed_password' => 'sealed-blob']);
        $client = new Client('psk_1', self::BASE, $mock);

        $created = $client->platform->atlasIdApps->provisionedAccounts->create('a_1', ['email' => 'r@x.test', 'label' => 'rev'], null, 'x25519:AAAA');
        $this->assertSame('x25519:AAAA', $mock->requests[0]->getHeaderLine('X-Secret-Recipient'));
        $this->assertSame('sealed-blob', $created['sealed_password']);
        $this->assertArrayNotHasKey('password', $created);

        $client->platform->atlasIdApps->provisionedAccounts->rotatePassword('a_1', 'p_1', null, 'x25519:AAAA');
        $this->assertSame('x25519:AAAA', $mock->requests[1]->getHeaderLine('X-Secret-Recipient'));
    }

    public function testNoRecipientHeaderByDefaultAndPasswordNotInExceptionText(): void
    {
        $mock = new MockClient();
        $mock->push(409, ['errors' => [['code' => 'PROVISIONED_ACCOUNT_LIMIT_REACHED', 'message' => 'limit']]]);
        $client = new Client('psk_1', self::BASE, $mock);
        try {
            $client->platform->atlasIdApps->provisionedAccounts->create('a_1', ['email' => 'r@x.test', 'label' => 'rev', 'password' => 'super-secret-pw-1']);
            $this->fail('expected an exception');
        } catch (\Atlas\Exception\AtlasApiException $e) {
            $this->assertTrue($e->hasCode(ErrorCode::PROVISIONED_ACCOUNT_LIMIT_REACHED));
            $this->assertStringNotContainsString('super-secret-pw-1', (string) $e);
        }
        $this->assertSame('', $mock->requests[0]->getHeaderLine('X-Secret-Recipient'));
    }

    public function testErrorCodes(): void
    {
        $this->assertSame('TEST_ACCOUNT_RESTRICTED', ErrorCode::TEST_ACCOUNT_RESTRICTED);
        $this->assertSame('PROVISIONED_ACCOUNT_LIMIT_REACHED', ErrorCode::PROVISIONED_ACCOUNT_LIMIT_REACHED);
        $this->assertSame('DOMAIN_NOT_VERIFIED', ErrorCode::DOMAIN_NOT_VERIFIED);
    }
}
