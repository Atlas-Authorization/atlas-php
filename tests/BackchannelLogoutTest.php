<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Tests\Support\Keys;
use Atlas\Tests\Support\MockClient;
use Atlas\Verify\BackchannelLogout;
use Atlas\Verify\InMemoryJtiStore;
use Atlas\Verify\InvalidLogoutTokenException;
use Atlas\Verify\JtiStore;
use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

/**
 * OIDC Back-Channel Logout token verification, signed with a throwaway RSA key
 * whose JWKS is served through the mocked PSR-18 client. No network.
 */
final class BackchannelLogoutTest extends TestCase
{
    private const ISSUER = 'https://atlasauth.net';
    private const CLIENT_ID = 'aid_client_1';

    private static ?Keys $keys = null;
    private int $now = 1_800_000_000;

    private static function keys(): Keys
    {
        return self::$keys ??= new Keys();
    }

    private function verifier(?JtiStore $store = null): BackchannelLogout
    {
        $mock = new MockClient();
        $mock->push(200, self::keys()->jwks);

        return new BackchannelLogout(
            issuer: self::ISSUER,
            jwksUrl: self::ISSUER . '/.well-known/jwks.json',
            clientId: self::CLIENT_ID,
            jtiStore: $store,
            httpClient: $mock,
            requestFactory: new HttpFactory(),
            now: fn (): int => $this->now,
        );
    }

    /**
     * @param array<string,mixed> $overrides a value of '__omit__' removes the claim
     */
    private function sign(array $overrides = [], ?string $typ = 'logout+jwt', ?string $pem = null): string
    {
        $claims = array_merge([
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'sub' => 'user_1',
            'sid' => 'sess_1',
            'iat' => $this->now,
            'exp' => $this->now + 120,
            'jti' => 'jti_' . bin2hex(random_bytes(6)),
            'events' => [BackchannelLogout::EVENT => new \stdClass()],
        ], $overrides);
        $claims = array_filter($claims, static fn ($v) => $v !== '__omit__');
        $header = ['typ' => $typ]; // null overrides the library default "JWT" with an untyped header

        return JWT::encode($claims, $pem ?? self::keys()->privateKeyPem, 'RS256', self::keys()->kid, $header);
    }

    private function assertRejected(string $token, string $reason, ?JtiStore $store = null): void
    {
        try {
            $this->verifier($store)->verify($token);
            $this->fail("expected rejection ({$reason})");
        } catch (InvalidLogoutTokenException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    public function testAcceptsAValidLogoutToken(): void
    {
        $claims = $this->verifier()->verify($this->sign(['jti' => 'jti_ok']));
        $this->assertSame('user_1', $claims->sub);
        $this->assertSame('sess_1', $claims->sid);
        $this->assertSame('jti_ok', $claims->jti);
        $this->assertSame($this->now, $claims->iat);
    }

    public function testAcceptsSubOnlyNoTypAndArrayAudience(): void
    {
        $claims = $this->verifier()->verify($this->sign(['sid' => '__omit__', 'aud' => ['other', self::CLIENT_ID]], null));
        $this->assertNull($claims->sid);
        $this->assertSame('user_1', $claims->sub);
    }

    public function testRejectsWrongTyp(): void
    {
        $this->assertRejected($this->sign([], 'JWT'), 'wrong_type');
    }

    public function testRejectsANonce(): void
    {
        $this->assertRejected($this->sign(['nonce' => 'n']), 'nonce_present');
    }

    public function testRejectsMissingEvents(): void
    {
        $this->assertRejected($this->sign(['events' => '__omit__']), 'missing_claim');
        $this->assertRejected($this->sign(['events' => ['other' => new \stdClass()]]), 'missing_claim');
    }

    public function testRejectsAnEventValueThatIsNotAnObject(): void
    {
        $this->assertRejected($this->sign(['events' => [BackchannelLogout::EVENT => 'yes']]), 'invalid_claim');
    }

    public function testRejectsWrongAudienceAndIssuer(): void
    {
        $this->assertRejected($this->sign(['aud' => 'someone_else']), 'wrong_audience');
        $this->assertRejected($this->sign(['iss' => 'https://evil.example']), 'wrong_issuer');
    }

    public function testRejectsExpiredAndOldTokensWithoutExp(): void
    {
        $this->assertRejected($this->sign(['iat' => $this->now - 600, 'exp' => $this->now - 300]), 'expired');
        $this->assertRejected($this->sign(['exp' => '__omit__', 'iat' => $this->now - 3600]), 'expired');
    }

    public function testRejectsIatInTheFutureAndMissingIat(): void
    {
        $this->assertRejected($this->sign(['iat' => $this->now + 3600, 'exp' => $this->now + 7200]), 'issued_in_future');
        $this->assertRejected($this->sign(['iat' => '__omit__']), 'missing_claim');
    }

    public function testRejectsMissingJtiAndMissingSubAndSid(): void
    {
        $this->assertRejected($this->sign(['jti' => '__omit__']), 'missing_claim');
        $this->assertRejected($this->sign(['sub' => '__omit__', 'sid' => '__omit__']), 'missing_claim');
    }

    public function testRejectsABadSignatureAndGarbage(): void
    {
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($other, $otherPem);
        $this->assertRejected($this->sign([], 'logout+jwt', $otherPem), 'invalid_signature');

        $hs = JWT::encode(['iss' => self::ISSUER], 'a-long-enough-shared-secret-for-hs256!!', 'HS256', self::keys()->kid);
        $this->assertRejected($hs, 'invalid_signature');
        $this->assertRejected('not-a-jwt', 'malformed');
    }

    public function testJtiStoreBlocksReplays(): void
    {
        $store = new InMemoryJtiStore(fn (): int => $this->now);
        $token = $this->sign(['jti' => 'once']);
        $this->verifier($store)->verify($token);
        $this->assertRejected($token, 'replayed', $store);
    }
}
