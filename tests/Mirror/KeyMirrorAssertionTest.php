<?php

declare(strict_types=1);

namespace Atlas\Tests\Mirror;

use Atlas\Auth\ApiKeyAssertion;
use Atlas\Http;
use Atlas\Mirror\KeyMirror;
use Atlas\Mirror\MemoryKeyMirrorStore;
use Atlas\Resources\ApiKeys;
use Atlas\Tests\Support\MockClient;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

/** The key mirror verifies key-pair assertions locally with the server's refusal codes; a disabled row is refused. */
final class KeyMirrorAssertionTest extends TestCase
{
    private int $offsetMs = 0;
    private MemoryKeyMirrorStore $store;
    private KeyMirror $mirror;
    private string $privatePem = '';

    protected function setUp(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($key, $this->privatePem);
        $this->store = new MemoryKeyMirrorStore();
        $this->store->put(['id' => 'key_1', 'hash' => 'key_pair:key_1', 'subject_type' => 'machine', 'subject_id' => 'm1', 'scopes' => ['read', 'write'],
            'allowed_ips' => null, 'expires_at' => null, 'revoked_at' => null, 'credential' => 'key_pair', 'audience' => 'ins_1',
            'public_key_pem' => openssl_pkey_get_details($key)['key']]);
        $f = new HttpFactory();
        $this->mirror = new KeyMirror($this->store, new ApiKeys(new Http('sk_test_x', 'https://api.example.test', new MockClient(), $f, $f)),
            now: fn (): int => time() * 1000 + $this->offsetMs);
    }

    private function sign(string $audience = 'ins_1', int $ttl = 300, ?string $scope = null, ?string $jti = null): string
    {
        return ApiKeyAssertion::sign($this->privatePem, 'key_1', $audience, $ttl, $scope, $jti);
    }

    public function testValidAssertionNarrowsScope(): void
    {
        $r = $this->mirror->verifyAssertion($this->sign(scope: 'read', jti: 'a'), ['requiredScopes' => ['read']]);
        self::assertTrue($r['valid']);
        self::assertSame('key_pair', $r['credential']);
        self::assertSame('a', $r['jti']);
        self::assertSame(['read'], $r['effective_scopes']);
        self::assertSame('scope_not_granted', $this->mirror->verifyAssertion($this->sign(scope: 'admin'))['code']);
    }

    public function testRefusalCodesAndJtiClaimedLast(): void
    {
        self::assertSame('wrong_audience', $this->mirror->verifyAssertion($this->sign('other'))['code']);
        self::assertSame('assertion_expired', $this->mirror->verifyAssertion($this->sign(ttl: -100))['code']);
        self::assertSame('assertion_lifetime_too_long', $this->mirror->verifyAssertion($this->sign(ttl: 7200))['code']);
        self::assertSame('invalid_assertion', $this->mirror->verifyAssertion('a.b.c')['code']);
        $token = $this->sign(jti: 'j1');
        self::assertSame('insufficient_scope', $this->mirror->verifyAssertion($token, ['requiredScopes' => ['nope']])['code']);
        self::assertTrue($this->mirror->verifyAssertion($token)['valid']);
        self::assertSame('assertion_replayed', $this->mirror->verifyAssertion($token)['code']);
        $this->offsetMs = -600_000;
        self::assertSame('assertion_not_yet_valid', $this->mirror->verifyAssertion($this->sign())['code']);
    }

    public function testDisabledRowsAreRefusedAndWebhookCopiesEnabled(): void
    {
        $this->store->put(['enabled' => false] + $this->store->get('key_1'));
        self::assertSame('invalid_assertion', $this->mirror->verifyAssertion($this->sign())['code']);
        self::assertTrue($this->mirror->applyWebhook(['type' => 'api_key.updated', 'data' => ['id' => 'key_1', 'enabled' => true]]));
        self::assertTrue($this->mirror->verifyAssertion($this->sign())['valid']);
        $this->store->markRevoked('key_1', 1);
        self::assertFalse($this->mirror->verifyAssertion($this->sign())['valid']);

        $this->store->put(['id' => 'k2', 'hash' => KeyMirror::hashSecret('s'), 'scopes' => [], 'expires_at' => null, 'revoked_at' => null,
            'subject_type' => 'service', 'subject_id' => 'x', 'enabled' => false]);
        self::assertFalse($this->mirror->verify('s')['valid']);
        $this->mirror->applyWebhook(['type' => 'api_key.updated', 'data' => ['id' => 'k2', 'enabled' => true]]);
        self::assertTrue($this->mirror->verify('s')['valid']);
    }
}
