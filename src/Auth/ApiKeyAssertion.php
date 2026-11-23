<?php

declare(strict_types=1);

namespace Atlas\Auth;

use Atlas\Exception\ConfigException;

/**
 * Signs the short-lived assertion a key-pair API key presents instead of a secret: ES256 for P-256
 * keys (raw `r || s`), EdDSA for Ed25519 keys (needs ext-sodium).
 */
final class ApiKeyAssertion
{
    private const ED25519_PKCS8_PREFIX = "\x30\x2e\x02\x01\x00\x30\x05\x06\x03\x2b\x65\x70\x04\x22\x04\x20";

    /** @param string $privateKeyPem the PKCS#8 PEM Atlas returned once at create */
    public static function sign(
        string $privateKeyPem,
        string $keyId,
        string $audience,
        int $ttlSeconds = 300,
        ?string $scope = null,
        ?string $jti = null,
    ): string {
        $b64 = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s/', '', $privateKeyPem) ?? '', true);
        if ($der === false) {
            throw new ConfigException('The private key is not a PEM.');
        }
        $ed = strlen($der) === 48 && str_starts_with($der, self::ED25519_PKCS8_PREFIX);
        $now = time();
        $claims = ['iss' => $keyId, 'aud' => $audience, 'iat' => $now, 'exp' => $now + $ttlSeconds, 'jti' => $jti ?? $b64(random_bytes(16))];
        if ($scope !== null) {
            $claims['scope'] = $scope;
        }
        $input = $b64(json_encode(['alg' => $ed ? 'EdDSA' : 'ES256', 'kid' => $keyId, 'typ' => 'JWT'], JSON_THROW_ON_ERROR))
            . '.' . $b64(json_encode($claims, JSON_THROW_ON_ERROR));

        if ($ed) {
            $signature = sodium_crypto_sign_detached($input, sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair(substr($der, 16))));
        } else {
            $key = openssl_pkey_get_private($privateKeyPem);
            $details = $key === false ? false : openssl_pkey_get_details($key);
            if ($key === false || ($details['ec']['curve_name'] ?? '') !== 'prime256v1') {
                throw new ConfigException('The key must be a P-256 or Ed25519 PKCS#8 private key.');
            }
            if (!openssl_sign($input, $derSignature, $key, OPENSSL_ALGO_SHA256)) {
                throw new ConfigException('Signing failed.');
            }
            $signature = self::derToRaw($derSignature);
        }

        return $input . '.' . $b64($signature);
    }

    /**
     * Split a compact JWS. Null when it is not three base64url segments of JSON objects.
     *
     * @return array{0:array<string,mixed>,1:array<string,mixed>,2:string,3:string}|null header, claims, signing input, signature
     */
    public static function parse(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        $raw = [];
        foreach ($parts as $part) {
            $d = preg_match('/^[A-Za-z0-9_-]*$/', $part) === 1 ? base64_decode(strtr($part, '-_', '+/'), true) : false;
            if ($d === false) {
                return null;
            }
            $raw[] = $d;
        }
        $header = json_decode($raw[0], true);
        $claims = json_decode($raw[1], true);

        return is_array($header) && is_array($claims) ? [$header, $claims, $parts[0] . '.' . $parts[1], $raw[2]] : null;
    }

    /** Check the signature with an SPKI PEM public key; `alg` must fit the key's type. */
    public static function verifySignature(string $alg, string $input, string $signature, string $publicKeyPem): bool
    {
        if ($alg === 'EdDSA') {
            $der = base64_decode(preg_replace('/-----[^-]+-----|\s/', '', $publicKeyPem) ?? '', true);
            if ($der !== false && strlen($der) === 44 && str_starts_with($der, "\x30\x2a\x30\x05\x06\x03\x2b\x65\x70\x03\x21\x00") && strlen($signature) === 64) {
                return sodium_crypto_sign_verify_detached($signature, $input, substr($der, 12));
            }
        }

        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false) {
            return false;
        }
        $details = openssl_pkey_get_details($key);
        if ($alg === 'ES256' && ($details['ec']['curve_name'] ?? '') === 'prime256v1') {
            if (strlen($signature) !== 64) {
                return false;
            }
            $enc = static function (string $b): string {
                $b = ltrim($b, "\0");
                $b = $b === '' ? "\0" : $b;
                if (ord($b[0]) > 127) {
                    $b = "\0" . $b;
                }

                return "\x02" . chr(strlen($b)) . $b;
            };
            $body = $enc(substr($signature, 0, 32)) . $enc(substr($signature, 32));

            return openssl_verify($input, "\x30" . chr(strlen($body)) . $body, $key, OPENSSL_ALGO_SHA256) === 1;
        }
        return false;
    }

    /** DER `SEQUENCE { INTEGER r, INTEGER s }` to fixed-width 64-byte `r || s`. */
    private static function derToRaw(string $der): string
    {
        $offset = ord($der[1]) & 0x80 ? 2 + (ord($der[1]) & 0x7f) : 2;
        $parts = [];
        for ($i = 0; $i < 2; ++$i) {
            $len = ord($der[$offset + 1]);
            $parts[] = str_pad(ltrim(substr($der, $offset + 2, $len), "\0"), 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $len;
        }

        return $parts[0] . $parts[1];
    }
}
