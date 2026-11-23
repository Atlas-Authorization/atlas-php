<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Auth\ApiKeyAssertion;
use PHPUnit\Framework\TestCase;

/** Key-pair API key assertions: header, claims, and a signature that verifies with the public key. */
final class ApiKeyAssertionTest extends TestCase
{
    /** @return array{0:array<string,mixed>,1:array<string,mixed>,2:string,3:string} */
    private function parts(string $jwt): array
    {
        [$h, $c, $s] = explode('.', $jwt);
        $dec = static fn (string $v): string => base64_decode(strtr($v, '-_', '+/'), true);

        return [json_decode($dec($h), true), json_decode($dec($c), true), $h . '.' . $c, $dec($s)];
    }

    public function testEs256IsRawRsAndVerifies(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($key, $pem);
        $jwt = ApiKeyAssertion::sign($pem, 'key_1', 'ins_1', scope: 'read', jti: 'j1');
        [$header, $claims, $input, $sig] = $this->parts($jwt);
        $this->assertSame(['alg' => 'ES256', 'kid' => 'key_1', 'typ' => 'JWT'], $header);
        $this->assertSame(['key_1', 'ins_1', 'read', 'j1'], [$claims['iss'], $claims['aud'], $claims['scope'], $claims['jti']]);
        $this->assertSame(300, $claims['exp'] - $claims['iat']);
        $this->assertSame(64, strlen($sig));
        // DER INTEGER: minimal (leading zero bytes stripped), with a 0x00 pad when the top bit is set.
        $int = static function (string $b): string {
            $b = ltrim($b, "\0");
            if ($b === '' || ord($b[0]) > 127) {
                $b = "\0" . $b;
            }
            return "\x02" . chr(strlen($b)) . $b;
        };
        $body = $int(substr($sig, 0, 32)) . $int(substr($sig, 32));
        $public = openssl_pkey_get_details($key)['key'];
        $this->assertSame(1, openssl_verify($input, "\x30" . chr(strlen($body)) . $body, $public, OPENSSL_ALGO_SHA256));
    }

    public function testEdDsaVerifies(): void
    {
        $pair = sodium_crypto_sign_keypair();
        $pem = "-----BEGIN PRIVATE KEY-----\n"
            . chunk_split(base64_encode("\x30\x2e\x02\x01\x00\x30\x05\x06\x03\x2b\x65\x70\x04\x22\x04\x20" . substr(sodium_crypto_sign_secretkey($pair), 0, 32)), 64)
            . "-----END PRIVATE KEY-----\n";
        [$header, $claims, $input, $sig] = $this->parts(ApiKeyAssertion::sign($pem, 'key_2', 'ins_1', ttlSeconds: 60));
        $this->assertSame('EdDSA', $header['alg']);
        $this->assertSame(60, $claims['exp'] - $claims['iat']);
        $this->assertNotSame('', $claims['jti']);
        $this->assertTrue(sodium_crypto_sign_verify_detached($sig, $input, sodium_crypto_sign_publickey($pair)));
    }
}
