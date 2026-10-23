<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Exception\ConfigException;
use Atlas\Psr15\AtlasAccessTokenMiddleware;
use Atlas\Tests\Support\MockClient;
use Atlas\Verify\AccessTokenResult;
use Atlas\Verify\AccessTokenVerifier;
use Atlas\Verify\Dpop;
use Atlas\Verify\HmacDpopNonceSource;
use Atlas\Verify\InMemoryJtiStore;
use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * DPoP (RFC 9449) on the access-token verifier: the same vectors as the
 * TypeScript @atlasauth/backend suite, plus every other refusal path. Keys are
 * generated per run; the proofs are genuine ES256 / RS256 / PS256 / EdDSA JWSs.
 */
final class DpopTest extends TestCase
{
    private const ISSUER = 'https://auth.example.com';
    private const API = 'https://api.example.com/mcp';
    private const META = 'https://api.example.com/.well-known/oauth-protected-resource/mcp';

    private string $asPem;
    /** @var array<string,mixed> */
    private array $jwks;

    protected function setUp(): void
    {
        $ec = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->assertNotFalse($ec);
        openssl_pkey_export($ec, $pem);
        $this->asPem = $pem;
        $d = openssl_pkey_get_details($ec);
        $this->jwks = ['keys' => [['kty' => 'EC', 'crv' => 'P-256', 'alg' => 'ES256', 'kid' => 'k1',
            'x' => self::b64(str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT)),
            'y' => self::b64(str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT))]]];
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /** @return array{alg:string,jwk:array<string,string>,jkt:string,sign:\Closure(string):string} */
    private static function key(string $alg = 'ES256'): array
    {
        if ($alg === 'EdDSA') {
            $kp = sodium_crypto_sign_keypair();
            $sk = sodium_crypto_sign_secretkey($kp);
            $jwk = ['kty' => 'OKP', 'crv' => 'Ed25519', 'x' => self::b64(sodium_crypto_sign_publickey($kp))];

            return ['alg' => $alg, 'jwk' => $jwk, 'jkt' => Dpop::jwkThumbprint($jwk),
                'sign' => static fn (string $in): string => sodium_crypto_sign_detached($in, $sk)];
        }
        if ($alg === 'ES256') {
            $pk = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
            $d = openssl_pkey_get_details($pk);
            $jwk = ['kty' => 'EC', 'crv' => 'P-256', 'x' => self::b64(str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT)),
                'y' => self::b64(str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT))];
            $sign = static function (string $in) use ($pk): string {
                openssl_sign($in, $der, $pk, 'sha256');

                return self::derToRaw($der, 32);
            };

            return ['alg' => $alg, 'jwk' => $jwk, 'jkt' => Dpop::jwkThumbprint($jwk), 'sign' => $sign];
        }
        $pk = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $d = openssl_pkey_get_details($pk);
        $jwk = ['kty' => 'RSA', 'n' => self::b64($d['rsa']['n']), 'e' => self::b64($d['rsa']['e'])];
        $sign = $alg === 'RS256'
            ? static function (string $in) use ($pk): string {
                openssl_sign($in, $sig, $pk, 'sha256');

                return $sig;
            }
            : static fn (string $in): string => self::pssSign($pk, strlen(ltrim($d['rsa']['n'], "\0")), $in);

        return ['alg' => $alg, 'jwk' => $jwk, 'jkt' => Dpop::jwkThumbprint($jwk), 'sign' => $sign];
    }

    private static function derToRaw(string $der, int $size): string
    {
        $pos = 2;
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $out .= str_pad(ltrim(substr($der, $pos + 2, $len), "\0"), $size, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }

        return $out;
    }

    /** RSASSA-PSS-SIGN (SHA-256, MGF1-SHA-256, 32-byte salt) over raw RSA, independent of the verifier. */
    private static function pssSign(\OpenSSLAsymmetricKey $pk, int $k, string $in): string
    {
        $emLen = $k; // 2048-bit modulus: emBits = 2047, emLen = 256
        $mHash = hash('sha256', $in, true);
        $salt = random_bytes(32);
        $h = hash('sha256', str_repeat("\0", 8) . $mHash . $salt, true);
        $db = str_repeat("\0", $emLen - 32 - 32 - 2) . "\x01" . $salt;
        $mask = '';
        for ($i = 0; strlen($mask) < strlen($db); $i++) {
            $mask .= hash('sha256', $h . pack('N', $i), true);
        }
        $masked = $db ^ substr($mask, 0, strlen($db));
        $masked[0] = chr(ord($masked[0]) & 0x7f);
        openssl_private_encrypt($masked . $h . "\xbc", $sig, $pk, OPENSSL_NO_PADDING);

        return $sig;
    }

    /**
     * @param array{alg:string,jwk:array<string,string>,jkt:string,sign:\Closure(string):string} $k
     * @param array<string,mixed>|null $header
     * @param array<string,mixed>      $claims null values delete the default
     */
    private static function proof(array $k, string $token, array $claims = [], ?array $header = null, bool $tamper = false): string
    {
        $body = ['htm' => 'POST', 'htu' => self::API, 'jti' => bin2hex(random_bytes(8)), 'iat' => time(),
            'ath' => Dpop::accessTokenHash($token)];
        foreach ($claims as $c => $v) {
            if ($v === null) {
                unset($body[$c]);
            } else {
                $body[$c] = $v;
            }
        }
        $h = $header ?? ['typ' => 'dpop+jwt', 'alg' => $k['alg'], 'jwk' => $k['jwk']];
        $enc = static fn (array $v): string => self::b64(json_encode($v, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $input = $enc($h) . '.' . $enc($body);
        $sig = ($k['sign'])($input);
        if ($tamper) {
            $input = $enc($h) . '.' . $enc(['htm' => 'POST', 'jti' => 'x']);
        }

        return $input . '.' . self::b64($sig);
    }

    private function token(?string $jkt = null): string
    {
        $claims = ['iss' => self::ISSUER, 'sub' => 'user_1', 'aud' => self::API, 'exp' => time() + 300,
            'token_use' => 'access_token', 'scope' => 'tools:read', 'client_id' => 'cli_1', 'jti' => bin2hex(random_bytes(6))];
        if ($jkt !== null) {
            $claims['cnf'] = ['jkt' => $jkt];
        }

        return JWT::encode($claims, $this->asPem, 'ES256', 'k1');
    }

    private function verifier(mixed ...$opts): AccessTokenVerifier
    {
        $mock = new MockClient();
        $mock->push(200, $this->jwks);

        return new AccessTokenVerifier(...array_merge([
            'issuer' => self::ISSUER,
            'audience' => self::API,
            'resourceMetadataUrl' => self::META,
            'httpClient' => $mock,
            'requestFactory' => new HttpFactory(),
        ], $opts));
    }

    private static function check(AccessTokenVerifier $v, string $scheme, string $tok, ?string $proof, string $method = 'POST', string $url = self::API . '?x=1'): AccessTokenResult
    {
        return $v->verify("{$scheme} {$tok}", [], $method, $url, $proof);
    }

    private function assertRefused(AccessTokenResult $r, string $error, string $name = ''): void
    {
        $this->assertFalse($r->ok, $name);
        $this->assertSame(401, $r->status, $name);
        $this->assertSame($error, $r->error, "{$name}: {$r->description}");
        $this->assertStringStartsWith('DPoP ', $r->wwwAuthenticate, $name);
        $this->assertStringContainsString("error=\"{$error}\"", $r->wwwAuthenticate, $name);
        $this->assertStringContainsString('algs="ES256', $r->wwwAuthenticate, $name);
    }

    public function testAcceptsBoundTokenWithMatchingProofForEveryAlg(): void
    {
        $v = $this->verifier();
        foreach (['ES256', 'RS256', 'PS256', 'EdDSA'] as $alg) {
            $k = self::key($alg);
            $tok = $this->token($k['jkt']);
            $r = self::check($v, 'DPoP', $tok, self::proof($k, $tok));
            $this->assertTrue($r->ok, "{$alg}: {$r->description}");
            $this->assertTrue($r->dpopBound);
            $this->assertSame('user_1', $r->subject);
        }
        $plain = self::check($v, 'Bearer', $this->token(), null);
        $this->assertTrue($plain->ok);
        $this->assertFalse($plain->dpopBound);
    }

    public function testNeverAcceptsBoundTokenAsBearer(): void
    {
        $k = self::key();
        $tok = $this->token($k['jkt']);
        $this->assertRefused(self::check($this->verifier(), 'Bearer', $tok, self::proof($k, $tok)), 'invalid_token');
        $this->assertRefused($this->verifier()->verify("DPoP {$tok}"), 'invalid_dpop_proof');
        $unbound = $this->token();
        $this->assertRefused(self::check($this->verifier(), 'DPoP', $unbound, self::proof($k, $unbound)), 'invalid_token');
    }

    public function testRejectsEveryBadProof(): void
    {
        $v = $this->verifier(dpopReplayStore: new InMemoryJtiStore());
        $k = self::key();
        $other = self::key();
        $tok = $this->token($k['jkt']);
        $now = time();
        $cases = [
            'missing proof' => null,
            'malformed' => 'not-a-jwt',
            'garbage header' => 'e30.e30.e30',
            'another key' => self::proof($other, $tok),
            'wrong htm' => self::proof($k, $tok, ['htm' => 'GET']),
            'wrong htu' => self::proof($k, $tok, ['htu' => 'https://api.example.com/other']),
            'wrong host' => self::proof($k, $tok, ['htu' => 'https://evil.example.com/mcp']),
            'relative htu' => self::proof($k, $tok, ['htu' => '/mcp']),
            'wrong ath' => self::proof($k, $tok, ['ath' => 'nope']),
            'no ath' => self::proof($k, $tok, ['ath' => null]),
            'proof for another token' => self::proof($k, $this->token($k['jkt'])),
            'stale' => self::proof($k, $tok, ['iat' => $now - 3600]),
            'future' => self::proof($k, $tok, ['iat' => $now + 60]),
            'no iat' => self::proof($k, $tok, ['iat' => null]),
            'expired exp' => self::proof($k, $tok, ['exp' => $now - 60]),
            'no jti' => self::proof($k, $tok, ['jti' => null]),
            'typ JWT' => self::proof($k, $tok, [], ['typ' => 'JWT', 'alg' => 'ES256', 'jwk' => $k['jwk']]),
            'alg HS256' => self::proof($k, $tok, [], ['typ' => 'dpop+jwt', 'alg' => 'HS256', 'jwk' => $k['jwk']]),
            'alg none' => self::proof($k, $tok, [], ['typ' => 'dpop+jwt', 'alg' => 'none', 'jwk' => $k['jwk']]),
            'no jwk' => self::proof($k, $tok, [], ['typ' => 'dpop+jwt', 'alg' => 'ES256']),
            'private jwk' => self::proof($k, $tok, [], ['typ' => 'dpop+jwt', 'alg' => 'ES256', 'jwk' => $k['jwk'] + ['d' => 'AAAA']]),
            'alg/key mismatch' => self::proof(self::key('RS256'), $tok, [], ['typ' => 'dpop+jwt', 'alg' => 'RS256', 'jwk' => $k['jwk']]),
            'bad signature' => self::proof($k, $tok, [], null, true),
        ];
        foreach ($cases as $name => $proof) {
            $this->assertRefused(self::check($v, 'DPoP', $tok, $proof), 'invalid_dpop_proof', $name);
        }
        $once = self::proof($k, $tok, ['jti' => 'fixed']);
        $this->assertTrue(self::check($v, 'DPoP', $tok, $once)->ok);
        $this->assertRefused(self::check($v, 'DPoP', $tok, $once), 'invalid_dpop_proof', 'replay');
        $tokOther = $this->token($other['jkt']);
        $this->assertTrue(self::check($v, 'DPoP', $tokOther, self::proof($other, $tokOther, ['jti' => 'fixed']))->ok, 'same jti, other key');
    }

    /** A PS256 proof signed by the openssl CLI, so the PSS check is not only tested against this file's own signer. */
    public function testPs256ProofFromOpensslCli(): void
    {
        $bin = trim((string) shell_exec('command -v openssl 2>/dev/null'));
        if ($bin === '') {
            $this->markTestSkipped('openssl CLI not available');
        }
        $pk = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($pk, $pem);
        $d = openssl_pkey_get_details($pk);
        $jwk = ['kty' => 'RSA', 'n' => self::b64($d['rsa']['n']), 'e' => self::b64($d['rsa']['e'])];
        $keyFile = tempnam(sys_get_temp_dir(), 'dpk');
        $inFile = tempnam(sys_get_temp_dir(), 'dpi');
        file_put_contents($keyFile, $pem);
        $sign = static function (string $in) use ($bin, $keyFile, $inFile): string {
            file_put_contents($inFile, $in);

            return (string) shell_exec(escapeshellarg($bin) . ' dgst -sha256 -sigopt rsa_padding_mode:pss -sigopt rsa_pss_saltlen:digest -sign '
                . escapeshellarg($keyFile) . ' ' . escapeshellarg($inFile));
        };
        $k = ['alg' => 'PS256', 'jwk' => $jwk, 'jkt' => Dpop::jwkThumbprint($jwk), 'sign' => $sign];
        try {
            $tok = $this->token($k['jkt']);
            $r = self::check($this->verifier(), 'DPoP', $tok, self::proof($k, $tok));
            $this->assertTrue($r->ok, $r->description);
            $this->assertRefused(self::check($this->verifier(), 'DPoP', $tok, self::proof($k, $tok, [], null, true)), 'invalid_dpop_proof');
        } finally {
            @unlink($keyFile);
            @unlink($inFile);
        }
    }

    public function testRequireDpopRefusesUnboundToken(): void
    {
        $v = $this->verifier(requireDpop: true);
        $this->assertRefused(self::check($v, 'Bearer', $this->token(), null), 'invalid_token');
        $none = $v->verify(null);
        $this->assertSame(401, $none->status);
        $this->assertNull($none->error);
        $this->assertMatchesRegularExpression('/^DPoP .*algs="/', $none->wwwAuthenticate);
    }

    public function testHtuNormalisationAndPublicOrigin(): void
    {
        $k = self::key();
        $tok = $this->token($k['jkt']);
        $v = $this->verifier();
        foreach (['HTTPS://API.Example.COM:443/mcp', 'https://api.example.com/mcp?other=2#frag'] as $htu) {
            $this->assertTrue(self::check($v, 'DPoP', $tok, self::proof($k, $tok, ['htu' => $htu]))->ok, $htu);
        }
        $this->assertRefused(self::check($v, 'DPoP', $tok, self::proof($k, $tok), 'POST', '/mcp?x=1'), 'invalid_dpop_proof');
        $vo = $this->verifier(publicOrigin: 'https://api.example.com');
        $this->assertTrue(self::check($vo, 'DPoP', $tok, self::proof($k, $tok), 'post', '/mcp?x=1')->ok);
        foreach ([
            'https://A.example.com:443' => 'https://a.example.com/',
            'http://a.example.com:80/x?y#z' => 'http://a.example.com/x',
            'https://a.example.com:8443/p/' => 'https://a.example.com:8443/p/',
            'https://[::1]:443/p' => 'https://[::1]/p',
        ] as $given => $want) {
            $this->assertSame($want, Dpop::normalizeHtu($given), $given);
        }
        $this->assertNull(Dpop::normalizeHtu('ftp://x/y'));
    }

    public function testServerNonce(): void
    {
        $clock = time();
        $nonces = new HmacDpopNonceSource(str_repeat('s', 32), 60, static function () use (&$clock): int {
            return $clock;
        });
        $v = $this->verifier(dpopNonces: $nonces);
        $k = self::key();
        $tok = $this->token($k['jkt']);
        $r = self::check($v, 'DPoP', $tok, self::proof($k, $tok));
        $this->assertRefused($r, 'use_dpop_nonce');
        $this->assertSame($nonces->current(), $r->dpopNonce);
        $this->assertRefused(self::check($v, 'DPoP', $tok, self::proof($k, $tok, ['nonce' => 'made-up'])), 'use_dpop_nonce');
        $ok = self::check($v, 'DPoP', $tok, self::proof($k, $tok, ['nonce' => $r->dpopNonce]));
        $this->assertTrue($ok->ok, $ok->description);
        $this->assertNotNull($ok->dpopNonce);
        $issued = $nonces->current();
        $clock += 60;
        $this->assertTrue($nonces->valid($issued));
        $clock += 60;
        $this->assertFalse($nonces->valid($issued));
        $this->assertRefused(self::check($v, 'DPoP', $tok, self::proof($k, $tok, ['nonce' => $issued])), 'use_dpop_nonce');
        $this->expectException(ConfigException::class);
        new HmacDpopNonceSource('short');
    }

    public function testHelpers(): void
    {
        $n = '0vx7agoebGcQSuuPiLJXZptN9nndrQmbXEps2aiAFbWhM78LhWx4cbbfAAtVT86zwu1RK7aPFFxuhDR1L6tSoc_BJECPebWKRXjBZCiFV4n3oknjhMstn64tZ_2W-5JsGY4Hc5n9yBXArwl93lqt7_RN5w6Cf0h4QyQ5v-65YGjQR0_FDW2QvzqY368QQMicAtaSqzs8KJZgnYb9c7d0zgdAZHzu6qMQvRL5hajrn1n91CbOpbISD08qNLyrdkt-bFTWhAI4vMQFh6WeZu0fM4lFd2NcRwr3XPksINHaQ-G_xBniIqbw0Ls1jF44-csFCur-kEgU8awapJzKnqDKgw';
        $this->assertSame(
            'NzbLsXh8uDCcd-6MNwXF4W_7noWXFZAfHkxZsRGC9Xs',
            Dpop::jwkThumbprint(['kty' => 'RSA', 'e' => 'AQAB', 'alg' => 'RS256', 'kid' => '2011-04-29', 'n' => $n]),
        );
        try {
            Dpop::jwkThumbprint(['kty' => 'oct', 'k' => 'x']);
            $this->fail('oct thumbprint computed');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        $doc = AccessTokenVerifier::protectedResourceMetadata(self::API, self::ISSUER, null, true);
        $this->assertTrue($doc['dpop_bound_access_tokens_required']);
        $this->assertSame(Dpop::ALGS, $doc['dpop_signing_alg_values_supported']);
        $this->assertSame(
            'DPoP error="use_dpop_nonce", error_description="n", algs="' . implode(' ', Dpop::ALGS) . '"',
            AccessTokenVerifier::buildWwwAuthenticate(null, 'use_dpop_nonce', 'n', null, null, 'DPoP'),
        );
    }

    public function testPsr15MiddlewareDpopWithNonce(): void
    {
        $nonces = new HmacDpopNonceSource(str_repeat('k', 32));
        $v = $this->verifier(dpopNonces: $nonces);
        $k = self::key('EdDSA');
        $tok = $this->token($k['jkt']);
        $middleware = new AtlasAccessTokenMiddleware($v);
        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $r = $request->getAttribute('atlas_access_token');

                return new Response(200, [], $r->dpopBound ? 'bound' : 'plain');
            }
        };
        $send = static function (string $proof, ?string $second = null) use ($middleware, $handler, $tok): ResponseInterface {
            $req = (new ServerRequest('PUT', 'https://api.example.com/docs/1?draft=1'))
                ->withHeader('Authorization', "DPoP {$tok}")
                ->withHeader('DPoP', $proof);
            if ($second !== null) {
                $req = $req->withAddedHeader('DPoP', $second);
            }

            return $middleware->process($req, $handler);
        };
        $proof = static fn (?string $nonce, string $jti): string => self::proof($k, $tok, ['htm' => 'PUT',
            'htu' => 'https://api.example.com/docs/1', 'nonce' => $nonce, 'jti' => $jti]);
        $first = $send($proof(null, 'a'));
        $this->assertSame(401, $first->getStatusCode());
        $nonce = $first->getHeaderLine('DPoP-Nonce');
        $this->assertNotSame('', $nonce);
        $this->assertStringStartsWith('DPoP error="use_dpop_nonce"', $first->getHeaderLine('WWW-Authenticate'));
        $ok = $send($proof($nonce, 'b'));
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertSame('bound', (string) $ok->getBody());
        $this->assertNotSame('', $ok->getHeaderLine('DPoP-Nonce'));
        $replay = $send($proof($nonce, 'b'));
        $this->assertSame(401, $replay->getStatusCode());
        $this->assertStringContainsString('error="invalid_dpop_proof"', $replay->getHeaderLine('WWW-Authenticate'));
        $this->assertSame(401, $send($proof($nonce, 'c'), $proof($nonce, 'd'))->getStatusCode());
    }
}
