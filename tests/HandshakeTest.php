<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Handshake\Handshake;
use Atlas\Handshake\HandshakeSession;
use Atlas\Tests\Support\MockClient;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

/**
 * The cross-property SSO handshake: PKCE (RFC 7636, S256) pair generation, the
 * PKCE-bound redeem call, and the return-URL param parsing / loop guard.
 *
 * PKCE is a security requirement here — the nonce rides a loggable return URL,
 * so it must not be a bearer credential on its own. These tests pin the exact
 * wire the Atlas server now verifies against.
 */
final class HandshakeTest extends TestCase
{
    /** base64url decode helper for asserting verifier entropy. */
    private static function base64UrlDecode(string $s): string
    {
        return base64_decode(strtr($s, '-_', '+/'), true) ?: '';
    }

    public function testCreatePkcePairProducesS256Binding(): void
    {
        $pair = Handshake::createPkcePair();

        $this->assertArrayHasKey('verifier', $pair);
        $this->assertArrayHasKey('challenge', $pair);

        $verifier = $pair['verifier'];
        $challenge = $pair['challenge'];

        // base64url alphabet only, and NO padding on either half.
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $verifier, 'verifier must be base64url');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $challenge, 'challenge must be base64url');
        $this->assertStringNotContainsString('=', $verifier, 'verifier must not be padded');
        $this->assertStringNotContainsString('=', $challenge, 'challenge must not be padded');
        $this->assertStringNotContainsString('+', $verifier);
        $this->assertStringNotContainsString('/', $verifier);

        // Verifier is 32 random bytes → 43 base64url chars (44 minus one '=' of padding).
        $this->assertSame(32, strlen(self::base64UrlDecode($verifier)), 'verifier must carry 32 bytes of entropy');
        $this->assertSame(43, strlen($verifier));

        // The S256 relation the server checks: challenge === base64url(sha256(verifier)), no padding.
        $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $this->assertSame($expected, $challenge);
    }

    public function testCreatePkcePairIsRandomPerCall(): void
    {
        $a = Handshake::createPkcePair();
        $b = Handshake::createPkcePair();

        $this->assertNotSame($a['verifier'], $b['verifier']);
        $this->assertNotSame($a['challenge'], $b['challenge']);
    }

    public function testRedeemSendsCodeVerifierInBody(): void
    {
        $mock = new MockClient();
        $mock->push(200, [
            'jwt' => 'ey.header.body',
            'refresh_token' => 'rt_abc',
            'session_id' => 'sess_1',
            'expires_in' => 3600,
        ]);

        $handshake = new Handshake($mock, new HttpFactory(), new HttpFactory());
        $session = $handshake->redeemHandshake(
            fapiOrigin: 'https://id.atlas.test',
            publishableKey: 'pk_test',
            userId: 'user_1',
            nonce: 'nonce_xyz',
            codeVerifier: 'verifier_secret',
        );

        $this->assertInstanceOf(HandshakeSession::class, $session);
        $this->assertSame('ey.header.body', $session->jwt);
        $this->assertSame('rt_abc', $session->refreshToken);
        $this->assertSame('sess_1', $session->sessionId);
        $this->assertSame(3600, $session->expiresIn);

        $request = $mock->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://id.atlas.test/v1/client/handshake/redeem', (string) $request->getUri());
        $this->assertSame('pk_test', $request->getHeaderLine('X-Publishable-Key'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));

        // The PKCE verifier must ride the redeem body alongside user_id and nonce.
        $this->assertSame(
            ['user_id' => 'user_1', 'nonce' => 'nonce_xyz', 'code_verifier' => 'verifier_secret'],
            json_decode((string) $request->getBody(), true),
        );
    }

    public function testRedeemReturnsNullOnRejectionWithoutThrowing(): void
    {
        $mock = new MockClient();
        // A wrong/leaked verifier, or a spent nonce, comes back as a 4xx.
        $mock->push(401, ['errors' => [['code' => 'INVALID_HANDSHAKE']]]);

        $handshake = new Handshake($mock, new HttpFactory(), new HttpFactory());
        $session = $handshake->redeemHandshake(
            fapiOrigin: 'https://id.atlas.test',
            publishableKey: 'pk_test',
            userId: 'user_1',
            nonce: 'nonce_xyz',
            codeVerifier: 'wrong_verifier',
        );

        $this->assertNull($session, 'a rejected redeem must return null, never throw');
    }

    public function testHandshakeRedirectUrlCarriesChallengeAndExposesVerifier(): void
    {
        $result = Handshake::handshakeRedirectUrl(
            'https://id.atlas.test/',
            'pk_test',
            'https://satellite.test/dashboard?x=1',
        );

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('verifier', $result);

        // The verifier is exposed for the caller to stash, and never in the URL.
        $this->assertStringNotContainsString($result['verifier'], $result['url']);
        $this->assertStringNotContainsString('code_verifier', $result['url']);

        // Parse the query and confirm the challenge is present and binds the verifier.
        $query = parse_url($result['url'], PHP_URL_QUERY);
        $this->assertIsString($query);
        parse_str($query, $params);

        $this->assertSame('pk_test', $params['publishable_key']);
        $this->assertSame('https://satellite.test/dashboard?x=1', $params['redirect_url']);
        $this->assertArrayHasKey('code_challenge', $params);

        $expectedChallenge = rtrim(strtr(base64_encode(hash('sha256', $result['verifier'], true)), '+/', '-_'), '=');
        $this->assertSame($expectedChallenge, $params['code_challenge']);

        $this->assertStringStartsWith('https://id.atlas.test/v1/client/handshake?', $result['url']);
    }

    public function testReadHandshakeParamsExtractsUserAndNonce(): void
    {
        $params = Handshake::readHandshakeParams(
            '/atlas/handshake?__atlas_hs=ok&__atlas_hu=user_1&__atlas_hn=nonce_xyz',
        );

        $this->assertSame(['userId' => 'user_1', 'nonce' => 'nonce_xyz'], $params);
    }
}
