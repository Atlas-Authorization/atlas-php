<?php

declare(strict_types=1);

namespace Atlas\Tests\Rp;

use Atlas\Rp\MemorySidStore;
use Atlas\Rp\RelyingParty;
use Atlas\Rp\RpException;
use Atlas\Rp\SealedCookie;
use Atlas\Tests\Support\Keys;
use Atlas\Tests\Support\MockClient;
use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

final class RelyingPartyTest extends TestCase
{
    private const ISSUER = 'https://atlasauth.net';
    private const NOW = 1_800_000_000;
    private const SECRET = 'a-session-secret-of-at-least-32-chars';

    private function discovery(): array
    {
        return [
            'issuer' => self::ISSUER,
            'authorization_endpoint' => self::ISSUER . '/oauth2/authorize',
            'token_endpoint' => self::ISSUER . '/oauth2/token',
            'jwks_uri' => self::ISSUER . '/oauth2/jwks',
            'end_session_endpoint' => self::ISSUER . '/oauth2/logout',
        ];
    }

    private function rp(MockClient $mock, ?MemorySidStore $sids = null): RelyingParty
    {
        $f = new HttpFactory();

        return new RelyingParty(
            issuer: self::ISSUER,
            clientId: 'client_1',
            redirectUri: 'https://app.example.com/auth/callback',
            secret: self::SECRET,
            clientSecret: 'shh',
            postLogoutRedirectUri: 'https://app.example.com/',
            forwardParams: ['ref'],
            httpClient: $mock,
            requestFactory: $f,
            streamFactory: $f,
            now: fn (): int => self::NOW,
            sidStore: $sids,
        );
    }

    private function idToken(Keys $keys, array $over = []): string
    {
        return JWT::encode($over + ['iss' => self::ISSUER, 'aud' => 'client_1', 'sub' => 'user_1', 'sid' => 'sid_1', 'iat' => self::NOW - 5, 'exp' => self::NOW + 300], $keys->privateKeyPem, 'RS256', $keys->kid);
    }

    public function testStartBuildsAPkceAuthorizeUrlAndSealsTheState(): void
    {
        $mock = new MockClient();
        $mock->push(200, $this->discovery());
        $start = $this->rp($mock)->start(['return_to' => '/billing', 'prompt' => 'create', 'ref' => 'abc', 'junk' => 'x']);

        parse_str((string) parse_url($start['url'], PHP_URL_QUERY), $q);
        self::assertStringStartsWith(self::ISSUER . '/oauth2/authorize?', $start['url']);
        self::assertSame('code', $q['response_type']);
        self::assertSame('S256', $q['code_challenge_method']);
        self::assertSame('create', $q['prompt']);
        self::assertSame('abc', $q['ref']);
        self::assertArrayNotHasKey('junk', $q);
        $saved = (new SealedCookie(self::SECRET))->open($start['cookie'], self::NOW);
        self::assertSame($q['state'], $saved['state']);
        self::assertSame('/billing', $saved['return_to']);
        self::assertSame(rtrim(strtr(base64_encode(hash('sha256', $saved['verifier'], true)), '+/', '-_'), '='), $q['code_challenge']);
        self::assertStringContainsString('HttpOnly', $start['set_cookie']);
    }

    public function testAnOffSiteReturnToIsDropped(): void
    {
        $mock = new MockClient();
        $mock->push(200, $this->discovery());
        $start = $this->rp($mock)->start(['return_to' => '//evil.example']);
        self::assertSame('/', (new SealedCookie(self::SECRET))->open($start['cookie'], self::NOW)['return_to']);
    }

    public function testCallbackExchangesTheCodeAndVerifiesTheIdToken(): void
    {
        $keys = new Keys();
        $mock = new MockClient();
        $mock->push(200, $this->discovery());
        $rp = $this->rp($mock);
        $start = $rp->start(['return_to' => '/x']);
        $nonce = (new SealedCookie(self::SECRET))->open($start['cookie'], self::NOW)['nonce'];
        $mock->push(200, ['access_token' => 'at', 'id_token' => $this->idToken($keys, ['nonce' => $nonce]), 'token_type' => 'Bearer']);
        $mock->push(200, $keys->jwks);

        $done = $rp->callback(['code' => 'abc', 'state' => $start['state']], $start['cookie']);

        self::assertSame('user_1', $done['claims']['sub']);
        self::assertSame('/x', $done['return_to']);
        $token = $mock->requests[1];
        self::assertStringStartsWith('Basic ', $token->getHeaderLine('Authorization'));
        parse_str((string) $token->getBody(), $form);
        self::assertSame('authorization_code', $form['grant_type']);
        self::assertSame((new SealedCookie(self::SECRET))->open($start['cookie'], self::NOW)['verifier'], $form['code_verifier']);
    }

    /** @return array<string,array{0:array<string,mixed>,1:string}> */
    public static function badTokens(): array
    {
        return [
            'wrong nonce' => [['nonce' => 'other'], 'nonce_mismatch'],
            'wrong audience' => [['aud' => 'someone-else', 'nonce' => 'N'], 'id_token_invalid'],
            'wrong issuer' => [['iss' => 'https://evil.example', 'nonce' => 'N'], 'id_token_invalid'],
            'expired' => [['exp' => self::NOW - 600, 'nonce' => 'N'], 'id_token_invalid'],
        ];
    }

    /** @dataProvider badTokens */
    public function testAnInvalidIdTokenIsRefused(array $claims, string $code): void
    {
        $keys = new Keys();
        $mock = new MockClient();
        $mock->push(200, $this->discovery());
        $rp = $this->rp($mock);
        $start = $rp->start();
        $saved = (new SealedCookie(self::SECRET))->open($start['cookie'], self::NOW);
        if (($claims['nonce'] ?? null) === 'N') {
            $claims['nonce'] = $saved['nonce'];
        }
        $mock->push(200, ['id_token' => $this->idToken($keys, $claims)]);
        $mock->push(200, $keys->jwks);
        try {
            $rp->callback(['code' => 'c', 'state' => $start['state']], $start['cookie']);
            self::fail('expected RpException');
        } catch (RpException $e) {
            self::assertSame($code, $e->error);
        }
    }

    public function testStateMismatchAndMissingCookieAreRefused(): void
    {
        $mock = new MockClient();
        $mock->push(200, $this->discovery());
        $rp = $this->rp($mock);
        $start = $rp->start();
        foreach ([[['code' => 'c', 'state' => 'nope'], $start['cookie'], 'state_mismatch'], [['code' => 'c', 'state' => $start['state']], null, 'state_missing'], [['code' => 'c', 'state' => $start['state']], 'garbage', 'state_expired']] as [$q, $cookie, $code]) {
            try {
                $rp->callback($q, $cookie);
                self::fail('expected RpException');
            } catch (RpException $e) {
                self::assertSame($code, $e->error);
            }
        }
    }

    public function testAnIssuerErrorBecomesAnException(): void
    {
        $mock = new MockClient();
        $mock->push(200, $this->discovery());
        $rp = $this->rp($mock);
        $start = $rp->start();
        $this->expectException(RpException::class);
        $rp->callback(['error' => 'access_denied', 'state' => $start['state']], $start['cookie']);
    }

    public function testTouchIfDueSkipsWhenRecentAndEndsAnUnknownSid(): void
    {
        $mock = new MockClient();
        $rp = $this->rp($mock);
        self::assertSame('skipped', $rp->touchIfDue('sid_1', self::NOW - 10));
        $mock->push(200, ['status' => 'ended', 'reason' => 'not_found']);
        self::assertSame('ended', $rp->touchIfDue('sid_1', null));
        self::assertSame('/oauth2/session/touch', $mock->lastRequest()->getUri()->getPath());
        $mock->push(200, ['status' => 'touched']);
        self::assertSame('touched', $rp->touchIfDue('sid_1', self::NOW - 3600));
    }

    public function testSignOutUrlCarriesTheHintAndRedirect(): void
    {
        $mock = new MockClient();
        $mock->push(200, $this->discovery());
        $url = $this->rp($mock)->signOutUrl('idt', 's1');
        self::assertStringStartsWith(self::ISSUER . '/oauth2/logout?', $url);
        self::assertStringContainsString('id_token_hint=idt', $url);
        self::assertStringContainsString('post_logout_redirect_uri=https%3A%2F%2Fapp.example.com%2F', $url);
    }

    public function testSealedCookieRejectsTamperingAndExpiry(): void
    {
        $c = new SealedCookie(self::SECRET);
        $v = $c->seal(['a' => 1], 60, 1000);
        self::assertSame(1, $c->open($v, 1010)['a']);
        self::assertNull($c->open($v, 2000));
        self::assertNull($c->open(substr($v, 0, -2) . 'AA', 1010));
        self::assertNull((new SealedCookie(self::SECRET . 'x'))->open($v, 1010));
    }

    public function testBoundSessionsAreHandedToTheLogoutCallback(): void
    {
        $sids = new MemorySidStore(fn (): int => self::NOW);
        $sids->bind('sid_1', 'app_a', self::NOW + 100);
        $sids->bind('sid_1', 'app_old', self::NOW - 1);
        self::assertSame(['app_a'], $sids->take('sid_1'));
        self::assertSame([], $sids->take('sid_1'));
    }
}
