<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Verify\ForbiddenException;
use Atlas\Verify\SessionVerifier;
use Atlas\Tests\Support\Keys;
use Atlas\Tests\Support\MockClient;
use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

/**
 * The local-verify path: signature (RS256, pinned), issuer, expiry with skew,
 * the azp allowlist, the token-confusion guard, request extraction, and the
 * documented online slow path.
 */
final class SessionVerifierTest extends TestCase
{
    private Keys $keys;
    private int $now = 1_700_000_000;

    protected function setUp(): void
    {
        $this->keys = new Keys();
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function mint(array $overrides = []): string
    {
        $claims = array_merge([
            'iss' => 'https://issuer.test',
            'sub' => 'user_1',
            'sid' => 'sess_1',
            'iat' => $this->now - 5,
            'nbf' => $this->now - 5,
            'exp' => $this->now + 60,
        ], $overrides);

        return JWT::encode($claims, $this->keys->privateKeyPem, 'RS256', $this->keys->kid);
    }

    /**
     * @param list<string> $authorizedParties
     */
    private function verifier(MockClient $mock, array $authorizedParties = []): SessionVerifier
    {
        // Prime the mock with the JWKS the first verify() will fetch.
        $mock->push(200, $this->keys->jwks);

        return new SessionVerifier(
            jwksUrl: 'https://issuer.test/.well-known/jwks.json',
            issuer: 'https://issuer.test',
            authorizedParties: $authorizedParties,
            secretKey: 'sk_test',
            bapiBaseUrl: 'https://api.atlas.test',
            httpClient: $mock,
            requestFactory: new HttpFactory(),
            streamFactory: new HttpFactory(),
            now: fn (): int => $this->now,
        );
    }

    public function testValidTokenVerifies(): void
    {
        $mock = new MockClient();
        $result = $this->verifier($mock)->verify($this->mint());

        $this->assertTrue($result->ok);
        $this->assertNull($result->reason);
        $this->assertSame('user_1', $result->claims['sub']);
        $this->assertSame('sess_1', $result->claims['sid']);
    }

    public function testMalformedTokenIsRejectedWithoutAnyFetch(): void
    {
        $mock = new MockClient();
        $verifier = $this->verifier($mock);

        $result = $verifier->verify('not.a-jwt');
        $this->assertFalse($result->ok);
        $this->assertSame('malformed', $result->reason);
        $this->assertSame(0, $mock->requestCount(), 'a malformed token must never touch the network');
    }

    public function testWrongIssuerIsInvalid(): void
    {
        $mock = new MockClient();
        $result = $this->verifier($mock)->verify($this->mint(['iss' => 'https://evil.test']));
        $this->assertFalse($result->ok);
        $this->assertSame('invalid', $result->reason);
    }

    public function testExpiredTokenIsInvalid(): void
    {
        $mock = new MockClient();
        $result = $this->verifier($mock)->verify($this->mint(['exp' => $this->now - 100]));
        $this->assertFalse($result->ok);
        $this->assertSame('invalid', $result->reason);
    }

    public function testExpiryWithinClockSkewStillVerifies(): void
    {
        $mock = new MockClient();
        // Expired 3s ago — inside the 5s tolerance, so still accepted.
        $result = $this->verifier($mock)->verify($this->mint(['exp' => $this->now - 3]));
        $this->assertTrue($result->ok);
    }

    public function testTamperedSignatureIsInvalid(): void
    {
        $mock = new MockClient();
        $token = $this->mint();
        $tampered = substr($token, 0, -2) . (str_ends_with($token, 'AA') ? 'BB' : 'AA');

        $result = $this->verifier($mock)->verify($tampered);
        $this->assertFalse($result->ok);
        $this->assertSame('invalid', $result->reason);
    }

    public function testAuthorizedPartyAllowlist(): void
    {
        $mock = new MockClient();
        $verifier = $this->verifier($mock, ['app_a']);

        $ok = $verifier->verify($this->mint(['azp' => 'app_a']));
        $this->assertTrue($ok->ok);

        $bad = $verifier->verify($this->mint(['azp' => 'app_b']));
        $this->assertFalse($bad->ok);
        $this->assertSame('unauthorized_party', $bad->reason);
    }

    public function testTokenConfusionGuardRejectsOpTokens(): void
    {
        $mock = new MockClient();
        $verifier = $this->verifier($mock);

        $this->assertFalse($verifier->verify($this->mint(['token_use' => 'access_token']))->ok);
        $this->assertFalse($verifier->verify($this->mint(['aud' => 'some-rp']))->ok);
        // An explicit session marker, and an absent one, both pass.
        $this->assertTrue($verifier->verify($this->mint(['token_use' => 'session']))->ok);
        $this->assertTrue($verifier->verify($this->mint())->ok);
    }

    public function testHasAndProtectReadOrgClaims(): void
    {
        $mock = new MockClient();
        $result = $this->verifier($mock)->verify($this->mint([
            'org_role' => 'admin',
            'org_permissions' => ['billing:read', 'members:read'],
        ]));

        $this->assertTrue($result->has());
        $this->assertTrue($result->has(['role' => 'admin']));
        $this->assertTrue($result->has(['permission' => 'billing:read']));
        $this->assertTrue($result->has(['anyPermission' => ['x', 'members:read']]));
        $this->assertFalse($result->has(['permission' => 'billing:write']));
        $this->assertFalse($result->has(['allPermissions' => ['billing:read', 'billing:write']]));

        $this->assertSame('admin', $result->protect(['role' => 'admin'])['org_role']);

        $this->expectException(ForbiddenException::class);
        $result->protect(['permission' => 'billing:write']);
    }

    public function testAuthenticateRequestPrefersHeaderOverCookie(): void
    {
        $mock = new MockClient();
        $verifier = $this->verifier($mock);
        $token = $this->mint(['sub' => 'from_header']);

        $result = $verifier->authenticateRequest([
            'Authorization' => 'Bearer ' . $token,
            'Cookie' => '__session=some-other-token',
        ]);
        $this->assertTrue($result->ok);
        $this->assertSame('from_header', $result->claims['sub']);
    }

    public function testAuthenticateRequestReadsSessionCookie(): void
    {
        $mock = new MockClient();
        $verifier = $this->verifier($mock);
        $token = $this->mint(['sub' => 'from_cookie']);

        $result = $verifier->authenticateRequest(['cookie' => 'a=b; __session=' . $token . '; c=d']);
        $this->assertTrue($result->ok);
        $this->assertSame('from_cookie', $result->claims['sub']);
    }

    public function testAuthenticateRequestWithNoTokenIsMalformed(): void
    {
        $mock = new MockClient();
        $result = $this->verifier($mock)->authenticateRequest(['Accept' => 'application/json']);
        $this->assertFalse($result->ok);
        $this->assertSame('malformed', $result->reason);
    }

    public function testVerifyOnlineConfirmsWithTheServer(): void
    {
        $mock = new MockClient();
        $verifier = $this->verifier($mock); // pushes JWKS
        $mock->push(200, ['object' => 'token_verification', 'verified' => true, 'user_id' => 'user_1']);

        $result = $verifier->verifyOnline($this->mint());
        $this->assertTrue($result->ok);

        // The second request is the online check, authorized with the secret key.
        $onlineRequest = $mock->lastRequest();
        $this->assertSame('POST', $onlineRequest->getMethod());
        $this->assertSame('https://api.atlas.test/v1/tokens/verify', (string) $onlineRequest->getUri());
        $this->assertSame('Bearer sk_test', $onlineRequest->getHeaderLine('Authorization'));
    }

    public function testVerifyOnlineFailsClosedWhenServerRejects(): void
    {
        $mock = new MockClient();
        $verifier = $this->verifier($mock);
        $mock->push(200, ['object' => 'token_verification', 'verified' => false, 'reason' => 'revoked']);

        $result = $verifier->verifyOnline($this->mint());
        $this->assertFalse($result->ok);
        $this->assertSame('invalid', $result->reason);
    }
}
