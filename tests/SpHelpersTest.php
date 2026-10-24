<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Auth\OAuthFlowException;
use Atlas\Auth\OperatorLogin;
use Atlas\Privacy\PrivacyExecutor;
use Atlas\Psr15\BackchannelLogoutHandler;
use Atlas\Tests\Support\Keys;
use Atlas\Tests\Support\MockClient;
use Atlas\Verify\BackchannelLogout;
use Atlas\Verify\LogoutTokenClaims;
use Atlas\Webhook\Webhook;
use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/** Webhook v2 URL signatures, privacy executor verification, the operator device flow and the logout handler. */
final class SpHelpersTest extends TestCase
{
    private const SECRET = 'atlas_live_whsec_abc';
    private const URL = 'https://hooks.example.com/atlas';

    private static function sig(string $version, string $id, int $ts, string $body, ?string $url = null): string
    {
        $signed = $version === 'v2' ? "{$id}.{$ts}.{$url}.{$body}" : "{$id}.{$ts}.{$body}";

        return $version . ',' . base64_encode(hash_hmac('sha256', $signed, self::SECRET, true));
    }

    /** @return array<string,string> */
    private static function headers(string $id, int $ts, string $signature): array
    {
        return ['atlas-id' => $id, 'atlas-timestamp' => (string) $ts, 'atlas-signature' => $signature];
    }

    public function testWebhookV2CoversTheUrl(): void
    {
        $body = '{"type":"user.created"}';
        $ts = 1_800_000_000_000;
        $both = self::sig('v1', 'msg_1', $ts, $body) . ' ' . self::sig('v2', 'msg_1', $ts, $body, self::URL);
        $h = self::headers('msg_1', $ts, $both);

        $this->assertTrue(Webhook::verify($body, $h, self::SECRET, $ts)->verified);
        $this->assertTrue(Webhook::verify($body, $h, self::SECRET, $ts, url: self::URL)->verified);
        $this->assertTrue(Webhook::verify($body, $h, self::SECRET, $ts, url: self::URL, requireV2: true)->verified);

        // a delivery replayed to another URL: v2 fails, and requireV2 refuses the v1 entry
        $v2Only = self::headers('msg_1', $ts, self::sig('v2', 'msg_1', $ts, $body, self::URL));
        $this->assertSame('signature_mismatch', Webhook::verify($body, $v2Only, self::SECRET, $ts, url: 'https://evil.example.com/atlas')->reason);
        $this->assertSame('malformed_signature', Webhook::verify($body, self::headers('msg_1', $ts, self::sig('v1', 'msg_1', $ts, $body)), self::SECRET, $ts, url: self::URL, requireV2: true)->reason);
        // without the URL a v2-only header carries nothing to check
        $this->assertSame('malformed_signature', Webhook::verify($body, $v2Only, self::SECRET, $ts)->reason);
        // unknown versions are ignored
        $unknown = self::headers('msg_1', $ts, 'v9,AAAA ' . self::sig('v1', 'msg_1', $ts, $body));
        $this->assertTrue(Webhook::verify($body, $unknown, self::SECRET, $ts)->verified);
    }

    public function testPrivacyExecutorVerifiesCaseRequestsAndCompletions(): void
    {
        $body = '{"type":"closure","source":"atlas","caseRef":"req_1"}';
        $ts = 1_800_000_000_000;
        $h = self::headers('call_1', $ts, self::sig('v1', 'call_1', $ts, $body)) + ['traceparent' => '00-' . str_repeat('a', 32) . '-' . str_repeat('b', 16) . '-01'];
        $result = PrivacyExecutor::verifyCaseRequest($body, $h, self::SECRET, $ts);
        $this->assertTrue($result->verified);
        $this->assertSame('req_1', $result->event['caseRef']);
        $this->assertSame($h['traceparent'], PrivacyExecutor::traceparent($h));
        $this->assertNull(PrivacyExecutor::traceparent(['traceparent' => 'bad']));
        $this->assertFalse(PrivacyExecutor::verifyCaseRequest($body . ' ', $h, self::SECRET, $ts)->verified);

        $done = '{"type":"privacy_request.completed","data":{"id":"pr_1"}}';
        $dh = self::headers('evt_1', $ts, self::sig('v2', 'evt_1', $ts, $done, self::URL));
        $this->assertTrue(PrivacyExecutor::verifyCompletion($done, $dh, self::SECRET, self::URL, $ts)->verified);
        $other = '{"type":"user.created"}';
        $oh = self::headers('evt_2', $ts, self::sig('v1', 'evt_2', $ts, $other));
        $this->assertSame('unexpected_event', PrivacyExecutor::verifyCompletion($other, $oh, self::SECRET, null, $ts)->reason);
    }

    public function testOperatorDeviceFlowAndTokenExchange(): void
    {
        $mock = new MockClient();
        $mock->push(200, [
            'client_id' => 'cli', 'scope' => 'operator', 'device_authorization_endpoint' => 'https://api.atlas.test/oauth/device',
            'token_endpoint' => 'https://api.atlas.test/oauth/token',
        ]);
        $mock->push(200, ['device_code' => 'dc', 'user_code' => 'ABCD-EFGH', 'verification_uri' => 'https://atlasauth.net/device', 'expires_in' => 600, 'interval' => 1]);
        $mock->push(400, ['error' => 'authorization_pending']);
        $mock->push(400, ['error' => 'slow_down']);
        $mock->push(200, ['access_token' => 'op_tok', 'token_type' => 'Bearer', 'expires_in' => 3600]);
        $mock->push(200, ['access_token' => 'scoped', 'resource' => 'ins_1']);
        $mock->push(200, ['access_token' => 'partner']);
        $mock->push(200, ['results' => [['index' => 0, 'kind' => 'sk', 'outcome' => 'revoked']]]);
        $waits = [];
        $login = new OperatorLogin('https://api.atlas.test', $mock, new HttpFactory(), new HttpFactory(), static function (int $ms) use (&$waits): void {
            $waits[] = $ms;
        });

        $start = $login->start();
        $this->assertSame('ABCD-EFGH', $start['user_code']);
        $this->assertSame('client_id=cli&scope=operator', (string) $mock->lastRequest()->getBody());
        $tokens = $login->poll($start);
        $this->assertSame('op_tok', $tokens['access_token']);
        $this->assertSame([1000, 1000, 6000], $waits, 'slow_down adds five seconds');
        $this->assertStringContainsString('device_code=dc', (string) $mock->lastRequest()->getBody());

        $scoped = $login->exchange('op_tok', 'ins_1', ['users:read', 'users:write']);
        $this->assertSame('scoped', $scoped['access_token']);
        parse_str((string) $mock->lastRequest()->getBody(), $form);
        $this->assertSame('urn:ietf:params:oauth:grant-type:token-exchange', $form['grant_type']);
        $this->assertSame('users:read users:write', $form['scope']);
        $this->assertSame('ins_1', $form['resource']);

        $this->assertSame('partner', $login->partnerLinkToken('id', 'secret', 'users:read')['access_token']);
        parse_str((string) $mock->lastRequest()->getBody(), $form);
        $this->assertSame('client_credentials', $form['grant_type']);

        $this->assertSame('revoked', $login->revokeLeakedKeys(['atlas_live_sk_x'])['results'][0]['outcome']);
        $this->assertSame('https://api.atlas.test/v1/keys/revoke_leaked', (string) $mock->lastRequest()->getUri());
    }

    public function testOperatorFlowErrors(): void
    {
        $mock = new MockClient();
        $mock->push(400, ['error' => 'access_denied', 'error_description' => 'denied']);
        $login = new OperatorLogin('https://api.atlas.test', $mock, new HttpFactory(), new HttpFactory(), static fn (int $ms) => null);
        try {
            $login->poll(['config' => ['token_endpoint' => 'https://t', 'client_id' => 'c'], 'device_code' => 'd', 'expires_in' => 600, 'interval' => 1]);
            $this->fail('expected a refusal');
        } catch (OAuthFlowException $e) {
            $this->assertSame('access_denied', $e->error);
        }
        $mock->push(403, ['errors' => [['code' => 'INSUFFICIENT_SCOPE', 'message' => 'no']]]);
        try {
            $login->exchange('t', 'ins_1', 'x');
            $this->fail('expected a refusal');
        } catch (OAuthFlowException $e) {
            $this->assertSame('INSUFFICIENT_SCOPE', $e->error);
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testBackchannelLogoutHandler(): void
    {
        $keys = new Keys();
        $now = 1_800_000_000;
        $token = JWT::encode([
            'iss' => 'https://atlasauth.net', 'aud' => 'cid', 'sub' => 'user_1', 'sid' => 'sess_1', 'iat' => $now, 'exp' => $now + 120,
            'jti' => 'jti_1', 'events' => [BackchannelLogout::EVENT => new \stdClass()],
        ], $keys->privateKeyPem, 'RS256', $keys->kid, ['typ' => 'logout+jwt']);

        $verifier = static function (int $jwksStatus) use ($keys, $now): BackchannelLogout {
            $mock = new MockClient();
            $jwksStatus === 200 ? $mock->push(200, $keys->jwks) : $mock->push($jwksStatus, 'down');

            return new BackchannelLogout('https://atlasauth.net', 'https://atlasauth.net/jwks', 'cid', null, $mock, new HttpFactory(), static fn (): int => $now);
        };

        $seen = null;
        [$status] = $verifier(200)->handle($token, static function (LogoutTokenClaims $c) use (&$seen): void {
            $seen = $c->sid;
        });
        $this->assertSame(200, $status);
        $this->assertSame('sess_1', $seen);

        [$status] = $verifier(200)->handle('garbage', static fn () => null);
        $this->assertSame(400, $status);
        [$status, $headers] = $verifier(500)->handle($token, static fn () => null);
        $this->assertSame(503, $status);
        $this->assertArrayHasKey('Retry-After', $headers);
        [$status] = $verifier(200)->handle($token, static function (): void {
            throw new \RuntimeException('db down');
        });
        $this->assertSame(503, $status, 'a failing callback makes Atlas retry');

        $handler = new BackchannelLogoutHandler($verifier(200), new HttpFactory(), static fn () => null);
        $request = (new ServerRequest('POST', 'https://app.example.com/logout'))->withParsedBody(['logout_token' => $token]);
        $this->assertSame(200, $handler->handle($request)->getStatusCode());
        $this->assertSame(405, $handler->handle(new ServerRequest('GET', 'https://app.example.com/logout'))->getStatusCode());
    }
}
