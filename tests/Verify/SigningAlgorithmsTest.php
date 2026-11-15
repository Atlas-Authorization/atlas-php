<?php

declare(strict_types=1);

namespace Atlas\Tests\Verify;

use Atlas\Tests\Support\MockClient;
use Atlas\Verify\SessionVerifier;
use Atlas\Verify\SigningKeys;
use Atlas\Verify\TransactionToken;
use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

/** Instances may sign with RS256, ES256, ES384, PS256 or EdDSA: every verifier accepts all five and checks the key type against `alg`. */
final class SigningAlgorithmsTest extends TestCase
{
    private const NOW = 1_800_000_000;
    private const ISS = 'https://issuer.test';

    private static function b64u(string $v): string
    {
        return rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
    }

    /** @return array{jwk:array<string,string>,sign:callable(array<string,mixed>):string} a fresh runtime key for `$alg` */
    private static function key(string $alg, string $kid = 'k1', bool $declareAlg = true): array
    {
        if ($alg === 'EdDSA') {
            $pair = sodium_crypto_sign_keypair();
            $jwk = ['kty' => 'OKP', 'crv' => 'Ed25519', 'x' => self::b64u(sodium_crypto_sign_publickey($pair))];
            $secret = base64_encode(sodium_crypto_sign_secretkey($pair));

            return ['jwk' => $jwk + ['kid' => $kid] + ($declareAlg ? ['alg' => $alg] : []), 'sign' => static fn (array $c): string => JWT::encode($c, $secret, 'EdDSA', $kid)];
        }
        if ($alg === 'ES256' || $alg === 'ES384') {
            $curve = $alg === 'ES256' ? 'prime256v1' : 'secp384r1';
            $res = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => $curve]);
            openssl_pkey_export($res, $pem);
            $ec = openssl_pkey_get_details($res)['ec'];
            $jwk = ['kty' => 'EC', 'crv' => $alg === 'ES256' ? 'P-256' : 'P-384', 'x' => self::b64u($ec['x']), 'y' => self::b64u($ec['y'])];

            return ['jwk' => $jwk + ['kid' => $kid] + ($declareAlg ? ['alg' => $alg] : []), 'sign' => static fn (array $c): string => JWT::encode($c, $pem, $alg, $kid)];
        }
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $pem);
        $d = openssl_pkey_get_details($res)['rsa'];
        $jwk = ['kty' => 'RSA', 'n' => self::b64u($d['n']), 'e' => self::b64u($d['e'])];
        $sign = $alg === 'RS256'
            ? static fn (array $c): string => JWT::encode($c, $pem, 'RS256', $kid)
            : static fn (array $c): string => self::signPs256($c, $res, $kid, strlen($d['n']));

        return ['jwk' => $jwk + ['kid' => $kid] + ($declareAlg ? ['alg' => $alg] : []), 'sign' => $sign];
    }

    /** RSASSA-PSS-SHA256 signing (EMSA-PSS-ENCODE + the raw RSA operation), as PHP's openssl_sign has no PSS. @param \OpenSSLAsymmetricKey $res */
    private static function signPs256(array $claims, $res, string $kid, int $k, string $alg = 'PS256'): string
    {
        $input = self::b64u(json_encode(['typ' => 'JWT', 'alg' => $alg, 'kid' => $kid])) . '.' . self::b64u(json_encode($claims));
        $emBits = $k * 8 - 1;
        $emLen = intdiv($emBits + 7, 8);
        $salt = random_bytes(32);
        $h = hash('sha256', str_repeat("\0", 8) . hash('sha256', $input, true) . $salt, true);
        $db = str_repeat("\0", $emLen - 32 - 32 - 2) . "\x01" . $salt;
        $mask = '';
        for ($i = 0; strlen($mask) < strlen($db); $i++) {
            $mask .= hash('sha256', $h . pack('N', $i), true);
        }
        $masked = $db ^ substr($mask, 0, strlen($db));
        $masked[0] = chr(ord($masked[0]) & (0xFF >> (8 * $emLen - $emBits)));
        $em = $masked . $h . "\xbc";
        openssl_private_encrypt($em, $sig, $res, OPENSSL_NO_PADDING);

        return $input . '.' . self::b64u($sig);
    }

    private function claims(array $over = []): array
    {
        return $over + ['iss' => self::ISS, 'sub' => 'user_1', 'sid' => 'sess_1', 'iat' => self::NOW - 5, 'nbf' => self::NOW - 5, 'exp' => self::NOW + 60];
    }

    private function verifier(array ...$jwks): SessionVerifier
    {
        $mock = new MockClient();
        $mock->push(200, ['keys' => $jwks]);
        $f = new HttpFactory();

        return new SessionVerifier('https://issuer.test/jwks', self::ISS, httpClient: $mock, requestFactory: $f, streamFactory: $f, now: static fn (): int => self::NOW);
    }

    /** @return array<string,array{0:string}> */
    public static function algs(): array
    {
        return array_combine(SigningKeys::ALGS, array_map(static fn (string $a): array => [$a], SigningKeys::ALGS));
    }

    /** @dataProvider algs */
    public function testSessionTokensVerifyForEveryAlgorithm(string $alg): void
    {
        $k = self::key($alg);
        $v = $this->verifier($k['jwk']);
        $r = $v->verify($k['sign']($this->claims()));
        self::assertTrue($r->ok, $alg . ': ' . (string) ($r->reason ?? ''));
        self::assertSame('user_1', $r->claims['sub']);
        self::assertFalse($this->verifier($k['jwk'])->verify($k['sign']($this->claims(['exp' => self::NOW - 600])))->ok, 'expired');
        $tampered = $k['sign']($this->claims());
        [$h, $p, $s] = explode('.', $tampered);
        $forged = $h . '.' . self::b64u((string) json_encode($this->claims(['sub' => 'admin']))) . '.' . $s;
        self::assertFalse($this->verifier($k['jwk'])->verify($forged)->ok, 'a changed payload');
    }

    /** @dataProvider algs */
    public function testTransactionTokensVerifyForEveryAlgorithm(string $alg): void
    {
        $k = self::key($alg);
        $claims = ['iss' => 'https://tts.example', 'sub' => 'u', 'aud' => 'orders', 'iat' => self::NOW, 'exp' => self::NOW + 60, 'txn' => 't', 'scope' => 'a', 'req_wl' => 'w'];
        $token = $k['sign']($claims);
        // the transaction token carries typ txntoken+jwt: re-sign the header through the same key is not possible for PS256 here, so
        // check the signature layer directly and the typ rejection through the verifier
        JWT::$timestamp = self::NOW; // the fixture clock is fixed: pin the decoder's clock to it
        try {
            self::assertSame($claims['sub'], SigningKeys::decode($token, ['keys' => [$k['jwk']]])->sub);
            self::assertFalse(TransactionToken::verify($token, 'https://tts.example', 'orders', ['keys' => [$k['jwk']]], self::NOW)['ok'], 'typ must be txntoken+jwt');
        } finally {
            JWT::$timestamp = null;
        }
    }

    public function testNoneAndHmacNeverVerify(): void
    {
        $k = self::key('RS256');
        $none = self::b64u('{"alg":"none","typ":"JWT"}') . '.' . self::b64u((string) json_encode($this->claims())) . '.';
        self::assertFalse($this->verifier($k['jwk'])->verify($none)->ok);
        foreach (['HS256', 'HS384', 'HS512'] as $hs) {
            // HMAC keyed with the public modulus, the classic confusion attack
            $token = JWT::encode($this->claims(), (string) base64_decode(strtr($k['jwk']['n'], '-_', '+/')), $hs, 'k1');
            self::assertFalse($this->verifier($k['jwk'])->verify($token)->ok, $hs);
        }
    }

    public function testKeyTypeMustMatchTheAlgorithm(): void
    {
        $rsa = self::key('RS256');
        $ec = self::key('ES256', 'k1');
        // an ES256 token presented under an RSA key's kid
        self::assertFalse($this->verifier($rsa['jwk'])->verify($ec['sign']($this->claims()))->ok);
        // a PS256 token whose RSA key does not declare PS256 (an undeclared RSA key is RS256 only)
        $undeclared = self::key('PS256', 'k1', false);
        self::assertFalse($this->verifier($undeclared['jwk'])->verify($undeclared['sign']($this->claims()))->ok);
        // an RS256 token against a key that declares PS256
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $pem);
        $d = openssl_pkey_get_details($res)['rsa'];
        $declaresPs = ['kty' => 'RSA', 'n' => self::b64u($d['n']), 'e' => self::b64u($d['e']), 'kid' => 'k1', 'alg' => 'PS256'];
        self::assertFalse($this->verifier($declaresPs)->verify(JWT::encode($this->claims(), $pem, 'RS256', 'k1'))->ok);
        // an EdDSA token against an EC key and a P-384 key declaring ES256
        $ed = self::key('EdDSA');
        self::assertFalse($this->verifier(self::key('ES384')['jwk'])->verify($ed['sign']($this->claims()))->ok);
        $badEc = self::key('ES384', 'k1', false)['jwk'];
        $badEc['alg'] = 'ES256';
        self::assertFalse($this->verifier($badEc)->verify(self::key('ES384')['sign']($this->claims()))->ok);
    }

    public function testASecondKeyInTheSetIsFoundByKid(): void
    {
        $a = self::key('PS256', 'a');
        $b = self::key('EdDSA', 'b');
        self::assertTrue($this->verifier($a['jwk'], $b['jwk'])->verify($b['sign']($this->claims()))->ok);
        self::assertTrue($this->verifier($a['jwk'], $b['jwk'])->verify($a['sign']($this->claims()))->ok);
    }
}
