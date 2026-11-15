<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Tests\Support\Keys;
use Atlas\Verify\RecentAuth;
use Atlas\Verify\TransactionToken;
use Atlas\Verify\WebBotAuth;
use Atlas\Webhook\MemoryWebhookDedupeStore;
use Atlas\Webhook\WebhookDedupe;
use Atlas\Webhook\WebhookVerificationException;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

final class VerificationPrimitivesTest extends TestCase
{
    private const NOW = 1_800_000_000;

    public function testRecentAuthHelpers(): void
    {
        self::assertTrue(RecentAuth::authenticatedWithin(['auth_time' => self::NOW - 300], 300, self::NOW));
        self::assertFalse(RecentAuth::authenticatedWithin(['auth_time' => self::NOW - 301], 300, self::NOW));
        self::assertFalse(RecentAuth::authenticatedWithin([], 300, self::NOW));
        self::assertTrue(RecentAuth::usedMultiFactor(['acr' => 'urn:atlas:acr:mfa']));
        self::assertTrue(RecentAuth::usedMultiFactor(['amr' => ['pwd', 'mfa']]));
        self::assertFalse(RecentAuth::usedMultiFactor(['amr' => ['pwd']]));
        self::assertFalse(RecentAuth::mfaSatisfied(['mfa_satisfied' => false, 'mfa' => true]), 'the server verdict is final');
        self::assertTrue(RecentAuth::mfaSatisfied(['sso_connection_id' => 'sso_1']));
        self::assertTrue(RecentAuth::mfaOrgSatisfied(['org_mfa_required' => false]));
        self::assertFalse(RecentAuth::mfaOrgSatisfied(['org_mfa_required' => true]));
        self::assertTrue(RecentAuth::mfaOrgSatisfied(['org_mfa_required' => true, 'org_mfa_ok' => true, 'org_id' => 'org_1'], 'org_1'));
        self::assertFalse(RecentAuth::mfaOrgSatisfied(['org_mfa_required' => true, 'org_mfa_ok' => true, 'org_id' => 'org_2'], 'org_1'));
    }

    public function testWebhookDedupeDropsRetriesAndReleases(): void
    {
        $body = '{"id":"evt_1","type":"user.updated","data":{"id":"user_1"}}';
        $ts = (int) (microtime(true) * 1000);
        $headers = ['Atlas-Id' => 'evt_1', 'Atlas-Timestamp' => (string) $ts, 'Atlas-Signature' => 'v1,' . base64_encode(hash_hmac('sha256', "evt_1.$ts.$body", 'whsec_x', true))];
        $store = new MemoryWebhookDedupeStore();

        $first = WebhookDedupe::verify($body, $headers, 'whsec_x', $store);
        self::assertFalse($first->duplicate);
        self::assertSame('evt_1', $first->deliveryId);
        self::assertTrue(WebhookDedupe::verify($body, $headers, 'whsec_x', $store)->duplicate);
        $first->release();
        self::assertFalse(WebhookDedupe::verify($body, $headers, 'whsec_x', $store)->duplicate);
    }

    public function testWebhookDedupeNeverTouchesTheStoreForABadSignature(): void
    {
        $store = new MemoryWebhookDedupeStore();
        $this->expectException(WebhookVerificationException::class);
        try {
            WebhookDedupe::verify('{}', ['Atlas-Id' => 'e', 'Atlas-Timestamp' => (string) (time() * 1000), 'Atlas-Signature' => 'v1,AAAA'], 'whsec_x', $store);
        } finally {
            self::assertTrue($store->setIfAbsent('atlas-webhook:e', 60));
        }
    }

    public function testTransactionTokenChecksTypeAudienceAndClaims(): void
    {
        $keys = new Keys();
        $claims = ['iss' => 'https://tts.example', 'sub' => 'user_1', 'aud' => 'orders.example.com', 'iat' => self::NOW, 'exp' => self::NOW + 60, 'txn' => 't1', 'scope' => 'a b', 'req_wl' => 'wl'];
        $mint = static fn (array $c, string $typ = 'txntoken+jwt'): string => JWT::encode($c, $keys->privateKeyPem, 'RS256', $keys->kid, ['typ' => $typ]);

        $ok = TransactionToken::verify($mint($claims), 'https://tts.example', 'orders.example.com', $keys->jwks, self::NOW);
        self::assertTrue($ok['ok']);
        self::assertSame(['a', 'b'], $ok['scopes']);
        self::assertFalse(TransactionToken::verify($mint($claims), 'https://tts.example', 'other.example.com', $keys->jwks, self::NOW)['ok']);
        self::assertFalse(TransactionToken::verify($mint($claims, 'JWT'), 'https://tts.example', 'orders.example.com', $keys->jwks, self::NOW)['ok']);
        self::assertFalse(TransactionToken::verify($mint($claims), 'https://tts.example', 'orders.example.com', $keys->jwks, self::NOW + 3600)['ok']);
        unset($claims['txn']);
        self::assertStringContainsString('txn', TransactionToken::verify($mint($claims), 'https://tts.example', 'orders.example.com', $keys->jwks, self::NOW)['error']);
    }

    /** @return array{0:array<string,string>,1:string,2:array<string,mixed>} headers, url, jwk */
    private function signed(string $secret, string $public, string $url, int $created, ?string $nonce = null): array
    {
        $x = rtrim(strtr(base64_encode($public), '+/', '-_'), '=');
        $jwk = ['kty' => 'OKP', 'crv' => 'Ed25519', 'x' => $x];
        $keyId = WebBotAuth::thumbprint($jwk);
        $agent = '"https://bot.example"';
        $raw = '("@authority" "signature-agent");created=' . $created . ';expires=' . ($created + 60) . ';keyid="' . $keyId . '";tag="web-bot-auth"' . ($nonce !== null ? ';nonce="' . $nonce . '"' : '');
        $base = "\"@authority\": example.com\n\"signature-agent\": $agent\n\"@signature-params\": $raw";
        $sig = base64_encode(sodium_crypto_sign_detached($base, $secret));

        return [['Signature-Agent' => $agent, 'Signature-Input' => "sig1=$raw", 'Signature' => "sig1=:$sig:"], $url, $jwk];
    }

    public function testWebBotAuthVerifiesASignedRequest(): void
    {
        $pair = sodium_crypto_sign_keypair();
        [$headers, $url, $jwk] = $this->signed(sodium_crypto_sign_secretkey($pair), sodium_crypto_sign_publickey($pair), 'https://example.com/page', self::NOW);
        $fetched = [];
        $keys = static function (string $dir, bool $refresh) use ($jwk, &$fetched): array {
            $fetched[] = $dir;

            return [$jwk];
        };

        $r = WebBotAuth::verify('GET', $url, $headers, $keys, self::NOW);
        self::assertTrue($r['verified'], (string) ($r['reason'] ?? ''));
        self::assertSame('https://bot.example', $r['directory']);
        self::assertSame(['https://bot.example/.well-known/http-message-signatures-directory'], $fetched);

        $headers['Signature'] = 'sig1=:' . base64_encode(str_repeat('x', 64)) . ':';
        self::assertSame('bad_signature', WebBotAuth::verify('GET', $url, $headers, $keys, self::NOW)['reason']);
        self::assertSame('expired', WebBotAuth::verify('GET', $url, $headers, $keys, self::NOW + 1000)['reason']);
        self::assertFalse(WebBotAuth::verify('GET', $url, [], $keys, self::NOW)['signed']);
    }

    public function testWebBotAuthRefusesAWrongHostAndAReplayedNonce(): void
    {
        $pair = sodium_crypto_sign_keypair();
        [$headers, , $jwk] = $this->signed(sodium_crypto_sign_secretkey($pair), sodium_crypto_sign_publickey($pair), 'https://example.com/', self::NOW, 'n1');
        $keys = static fn (): array => [$jwk];
        self::assertSame('bad_signature', WebBotAuth::verify('GET', 'https://other.example/', $headers, $keys, self::NOW)['reason'], 'the signature binds @authority');
        $seen = [];
        $claim = static function (string $k) use (&$seen): bool {
            return !($seen[$k] ?? false) && ($seen[$k] = true);
        };
        self::assertTrue(WebBotAuth::verify('GET', 'https://example.com/', $headers, $keys, self::NOW, claimNonce: $claim)['verified']);
        self::assertSame('replayed', WebBotAuth::verify('GET', 'https://example.com/', $headers, $keys, self::NOW, claimNonce: $claim)['reason']);
    }

    public function testWebBotAuthReportsAnUnavailableDirectoryAndMissingKey(): void
    {
        $pair = sodium_crypto_sign_keypair();
        [$headers] = $this->signed(sodium_crypto_sign_secretkey($pair), sodium_crypto_sign_publickey($pair), 'https://example.com/', self::NOW);
        self::assertSame('directory_unavailable', WebBotAuth::verify('GET', 'https://example.com/', $headers, static fn (): ?array => null, self::NOW)['reason']);
        self::assertSame('key_not_found', WebBotAuth::verify('GET', 'https://example.com/', $headers, static fn (): array => [], self::NOW)['reason']);
    }
}
