<?php

declare(strict_types=1);

namespace Atlas\Tests\Mirror;

use Atlas\Http;
use Atlas\Mirror\Cidr;
use Atlas\Mirror\KeyMirror;
use Atlas\Mirror\MemoryKeyMirrorStore;
use Atlas\Mirror\MemoryResourceRoleMirrorStore;
use Atlas\Mirror\ResourceRoleMirror;
use Atlas\Mirror\SidRevocationList;
use Atlas\Resources\ApiKeys;
use Atlas\Tests\Support\MockClient;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

final class MirrorsTest extends TestCase
{
    private int $t = 1_000_000;

    private function http(MockClient $mock): Http
    {
        $f = new HttpFactory();

        return new Http('sk_test_x', 'https://api.example.test', $mock, $f, $f);
    }

    private function keyMirror(MockClient $mock, ?MemoryKeyMirrorStore $store = null, array $extra = []): KeyMirror
    {
        return new KeyMirror($store ?? new MemoryKeyMirrorStore(), new ApiKeys($this->http($mock)), ...($extra + ['now' => fn (): int => $this->t]));
    }

    public function testCidrMatchesV4AndV6(): void
    {
        self::assertTrue(Cidr::contains('10.1.2.3', ['10.0.0.0/8']));
        self::assertFalse(Cidr::contains('11.1.2.3', ['10.0.0.0/8']));
        self::assertTrue(Cidr::contains('192.168.1.5', ['192.168.1.5']));
        self::assertTrue(Cidr::contains('2001:db8::1', ['2001:db8::/32']));
        self::assertFalse(Cidr::contains('2001:db9::1', ['2001:db8::/32']));
        self::assertFalse(Cidr::contains('10.0.0.1', ['2001:db8::/32']));
        self::assertFalse(Cidr::contains('not-an-ip', ['10.0.0.0/8']));
        self::assertTrue(Cidr::contains('10.0.0.1', ['0.0.0.0/0']));
    }

    public function testMintedKeyVerifiesLocallyWithScopesAndRevocation(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['id' => 'eak_1', 'secret' => 'ak_live_secret', 'prefix' => 'ak_live_', 'subject_type' => 'user', 'subject_id' => 'user_1', 'scopes' => ['read'], 'expires_at' => null, 'revoked_at' => null]);
        $m = $this->keyMirror($mock, extra: ['scopeImplications' => ['write' => ['read']]]);
        $m->mint(['subject_type' => 'user', 'subject_id' => 'user_1']);

        $ok = $m->verify('ak_live_secret', ['requiredScopes' => ['read']]);
        self::assertTrue($ok['valid']);
        self::assertSame(['read'], $ok['effective_scopes']);
        self::assertSame('insufficient_scope', $m->verify('ak_live_secret', ['requiredScopes' => ['write']])['code']);
        self::assertFalse($m->verify('ak_live_other')['valid']);
        self::assertSame(1, $mock->requestCount(), 'verify makes no network call');

        $mock->push(200, ['id' => 'eak_1']);
        $m->revoke('eak_1');
        self::assertFalse($m->verify('ak_live_secret')['valid']);
    }

    public function testAllowedIpsFailClosed(): void
    {
        $mock = new MockClient();
        $store = new MemoryKeyMirrorStore();
        $store->put(['id' => 'eak_1', 'hash' => KeyMirror::hashSecret('s'), 'scopes' => [], 'allowed_ips' => ['10.0.0.0/8'], 'expires_at' => null, 'revoked_at' => null, 'subject_type' => 'service', 'subject_id' => 'x']);
        $m = $this->keyMirror($mock, $store);
        self::assertSame('client_ip_required', $m->verify('s')['code']);
        self::assertSame('ip_not_allowed', $m->verify('s', ['clientIp' => '8.8.8.8'])['code']);
        self::assertTrue($m->verify('s', ['clientIp' => '10.2.3.4'])['valid']);
    }

    public function testStaleMirrorRefusesUntilSynced(): void
    {
        $mock = new MockClient();
        $store = new MemoryKeyMirrorStore();
        $store->put(['id' => 'eak_1', 'hash' => KeyMirror::hashSecret('s'), 'scopes' => [], 'expires_at' => null, 'revoked_at' => null, 'subject_type' => 'service', 'subject_id' => 'x']);
        $m = $this->keyMirror($mock, $store, ['maxStalenessMs' => 1000]);
        self::assertSame('stale', $m->verify('s')['code']);
        $mock->push(200, ['data' => [['id' => 'eak_1', 'revoked_at' => 5]], 'has_more' => false, 'next_cursor' => 'c1']);
        self::assertSame(1, $m->sync());
        self::assertFalse($m->verify('s')['valid'], 'the feed revoked it');
        $this->t += 5000;
        self::assertSame('stale', $m->verify('s')['code']);
    }

    public function testWebhooksUpdateTheMirror(): void
    {
        $store = new MemoryKeyMirrorStore();
        $store->put(['id' => 'eak_1', 'hash' => 'h', 'scopes' => ['a'], 'expires_at' => null, 'revoked_at' => null, 'subject_type' => 'user', 'subject_id' => 'user_1', 'organization_id' => 'org_1']);
        $m = $this->keyMirror(new MockClient(), $store);
        self::assertTrue($m->applyWebhook(['type' => 'api_key.updated', 'data' => ['id' => 'eak_1', 'scopes' => ['b']]]));
        self::assertSame(['b'], $store->get('eak_1')['scopes']);
        self::assertTrue($m->applyWebhook(['type' => 'organization_membership.deleted', 'data' => ['user_id' => 'user_1', 'organization_id' => 'org_1']]));
        self::assertSame('membership_ended', $store->get('eak_1')['revoked_reason']);
        self::assertTrue($m->applyWebhook(['type' => 'api_key.restored', 'data' => ['id' => 'eak_1']]));
        self::assertNull($store->get('eak_1')['revoked_at']);
        self::assertFalse($m->applyWebhook(['type' => 'user.created', 'data' => []]));
    }

    public function testSidRevocationListPollsAndFailsClosedWhenStale(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['data' => [['sid' => 'sid_bad', 'revoked_at' => $this->t - 1000]], 'has_more' => false, 'next_cursor' => null, 'server_time' => $this->t]);
        $list = new SidRevocationList($this->http($mock), onStale: 'reject', staleAfterMs: 60_000, now: fn (): int => $this->t);
        self::assertTrue($list->isRevoked('sid_bad'));
        self::assertFalse($list->isRevoked('sid_ok'));
        self::assertFalse($list->unavailable());
        self::assertSame(1, $mock->requestCount(), 'one poll per interval');

        $this->t += 20_000;
        $mock->pushException();
        self::assertTrue($list->isRevoked('sid_bad'), 'a failed poll keeps the last list');
        $this->t += 100_000;
        $mock->pushException();
        $list->isRevoked('x');
        self::assertTrue($list->unavailable());
    }

    public function testResourceRoleMirrorResolvesInheritedRoles(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['data' => [['key' => 'folder', 'inherit' => true, 'roles' => [['key' => 'editor', 'permissions' => ['doc:edit']], ['key' => 'owner', 'permissions' => [], 'includes' => ['editor']]]]]]);
        $mock->push(200, ['data' => [['id' => 'r_folder', 'type' => 'folder', 'external_id' => 'f1', 'parent_id' => null], ['id' => 'r_doc', 'type' => 'folder', 'external_id' => 'd1', 'parent_id' => 'r_folder']], 'has_more' => false]);
        $mock->push(200, ['data' => [
            ['change' => 'created', 'assignment_id' => 'a1', 'resource_id' => 'r_folder', 'subject' => ['type' => 'org', 'id' => 'org_1'], 'role' => 'owner', 'inherit' => true],
            ['change' => 'created', 'assignment_id' => 'a2', 'resource_id' => 'r_doc', 'subject' => ['type' => 'user', 'id' => 'user_2'], 'role' => 'editor', 'inherit' => false],
        ], 'has_more' => false, 'next_cursor' => 'cur1']);
        $m = new ResourceRoleMirror(new MemoryResourceRoleMirrorStore(), $this->http($mock), maxStalenessMs: 10_000, now: fn (): int => $this->t);

        self::assertSame('stale', $m->check(['type' => 'user', 'id' => 'user_1'], 'doc:edit', ['id' => 'r_doc'])['code']);
        self::assertSame(2, $m->sync());

        $viaOrg = $m->check(['type' => 'user', 'id' => 'user_1'], 'doc:edit', ['type' => 'folder', 'external_id' => 'd1'], ['orgs' => ['org_1']]);
        self::assertTrue($viaOrg['allowed']);
        self::assertTrue($viaOrg['grants'][0]['inherited']);
        self::assertFalse($m->can(['type' => 'user', 'id' => 'user_1'], 'doc:edit', ['id' => 'r_doc']));
        self::assertTrue($m->can(['type' => 'user', 'id' => 'user_2'], 'doc:edit', ['id' => 'r_doc']));
        self::assertFalse($m->can(['type' => 'user', 'id' => 'user_2'], 'doc:edit', ['id' => 'r_folder']), 'a non-inheriting grant stays on its own resource');
        self::assertSame('unknown_resource', $m->check(['type' => 'user', 'id' => 'u'], 'p', ['id' => 'nope'])['code']);

        self::assertTrue($m->applyWebhook(['type' => 'resource_role_assignment.deleted', 'data' => ['id' => 'a1', 'resource_id' => 'r_folder', 'subject' => ['type' => 'org', 'id' => 'org_1'], 'role' => 'owner']]));
        self::assertFalse($m->can(['type' => 'user', 'id' => 'user_1'], 'doc:edit', ['id' => 'r_doc'], ['orgs' => ['org_1']]));
    }
}
