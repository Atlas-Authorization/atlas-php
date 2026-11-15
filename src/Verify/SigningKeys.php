<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * The JWS algorithms Atlas signs tokens with, and the JWKS parsing that pins
 * every verifier to them. An instance key signs with RS256 (the default), ES256, ES384,
 * PS256 or EdDSA. Each published key is bound to the ONE algorithm its type allows
 * (an RSA key → RS256, or PS256 when it declares so; P-256 → ES256; P-384 → ES384;
 * Ed25519 → EdDSA); a key whose own `alg` contradicts its type is dropped. So `alg: none`, HMAC, or
 * an algorithm coerced onto the key material never verifies.
 */
final class SigningKeys
{
    /** @var list<string> */
    public const ALGS = ['RS256', 'ES256', 'ES384', 'PS256', 'EdDSA'];

    /**
     * Verify a JWS against a JWKS: the header `alg` must be one of {@see ALGS} and the key must be of the type that algorithm needs.
     * Signature, `exp`, `nbf` and `iat` are checked (with `JWT::$leeway` and `JWT::$timestamp`, as `JWT::decode` does); `iss`/`aud` are
     * for the caller. `none` and HMAC never verify.
     *
     * @param array{keys:array<int,array<string,mixed>>} $jwks
     *
     * @throws \Throwable on any failure (ExpiredException, BeforeValidException, or an \UnexpectedValueException)
     */
    public static function decode(string $jwt, array $jwks, ?\stdClass &$header = null): object
    {
        $parts = explode('.', $jwt);
        $h = count($parts) === 3 ? json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true)) : null;
        if (!$h instanceof \stdClass || !is_string($h->alg ?? null) || !in_array($h->alg, self::ALGS, true)) {
            throw new \UnexpectedValueException('Unsupported algorithm.');
        }
        $header = $h;
        if ($h->alg !== 'PS256') {
            return JWT::decode($jwt, self::parse($jwks), $header);
        }
        $sig = base64_decode(strtr($parts[2], '-_', '+/'), true);
        $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true));
        if ($sig === false || !$payload instanceof \stdClass) {
            throw new \UnexpectedValueException('Malformed token.');
        }
        $kid = $h->kid ?? null;
        $ok = false;
        foreach ($jwks['keys'] as $jwk) {
            // A PS256 token needs an RSA key that DECLARES `alg: PS256`: an undeclared RSA key is an RS256 key.
            if (is_array($jwk) && ($jwk['kty'] ?? null) === 'RSA' && ($jwk['alg'] ?? null) === 'PS256' && ($kid === null || ($jwk['kid'] ?? null) === $kid)
                && self::verifyPss($parts[0] . '.' . $parts[1], $sig, $jwk)) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            throw new \UnexpectedValueException('Signature verification failed.');
        }
        $now = JWT::$timestamp ?? time();
        if (isset($payload->nbf) && $payload->nbf > $now + JWT::$leeway) {
            throw new BeforeValidException('Cannot handle token with nbf prior to now.');
        }
        if (isset($payload->iat) && $payload->iat > $now + JWT::$leeway) {
            throw new BeforeValidException('Cannot handle token with iat prior to now.');
        }
        if (isset($payload->exp) && $now - JWT::$leeway >= $payload->exp) {
            throw new ExpiredException('Expired token');
        }

        return $payload;
    }

    /**
     * @param array{keys:array<int,array<string,mixed>>} $jwks
     *
     * @return array<string,Key> keyed by kid (the keys php-jwt verifies: everything except PS256)
     */
    public static function parse(array $jwks): array
    {
        $keys = [];
        foreach ($jwks['keys'] as $jwk) {
            if (!is_array($jwk)) {
                continue;
            }
            $alg = self::algFor($jwk);
            if ($alg === null || $alg === 'PS256') {
                continue;
            }
            $jwk['alg'] = $alg;
            $keys[] = $jwk;
        }
        if ($keys === []) {
            throw new \UnexpectedValueException('No usable keys in the JWKS.');
        }

        return JWK::parseKeySet(['keys' => $keys]);
    }

    /** @param array<string,mixed> $jwk */
    private static function algFor(array $jwk): ?string
    {
        $declared = $jwk['alg'] ?? null;
        $alg = match ($jwk['kty'] ?? null) {
            'RSA' => $declared === 'PS256' ? 'PS256' : 'RS256',
            'EC' => match ($jwk['crv'] ?? null) {
                'P-256' => 'ES256',
                'P-384' => 'ES384',
                default => null,
            },
            'OKP' => ($jwk['crv'] ?? null) === 'Ed25519' ? 'EdDSA' : null,
            default => null,
        };

        return $alg !== null && ($declared === null || $declared === $alg) ? $alg : null;
    }

    /** RSASSA-PSS (SHA-256, MGF1-SHA256, 32-byte salt) verification through openssl's raw RSA operation. @param array<string,mixed> $jwk */
    private static function verifyPss(string $message, string $sig, array $jwk): bool
    {
        $n = base64_decode(strtr((string) ($jwk['n'] ?? ''), '-_', '+/'), true);
        $e = base64_decode(strtr((string) ($jwk['e'] ?? ''), '-_', '+/'), true);
        if ($n === false || $e === false || $n === '' || $e === '') {
            return false;
        }
        $k = strlen(ltrim($n, "\0"));
        if ($k < 128 || strlen($sig) !== $k) {
            return false; // shortcut: refuses RSA keys under 1024 bits
        }
        $der = static fn (int $tag, string $c): string => chr($tag) . (strlen($c) < 128 ? chr(strlen($c)) : (strlen($c) < 256 ? "\x81" . chr(strlen($c)) : "\x82" . pack('n', strlen($c)))) . $c;
        $int = static fn (string $v): string => $der(0x02, (ord($v[0]) & 0x80 ? "\0" : '') . ltrim($v, "\0"));
        $spki = $der(0x30, $der(0x30, "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00") . $der(0x03, "\0" . $der(0x30, $int($n) . $int($e))));
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
        if (!openssl_public_decrypt($sig, $raw, $pem, OPENSSL_NO_PADDING) || $raw === '') {
            return false;
        }
        $modBits = $k * 8 - (8 - self::topBits(ltrim($n, "\0")));
        $emBits = $modBits - 1;
        $emLen = intdiv($emBits + 7, 8);
        $em = substr(str_pad($raw, $k, "\0", STR_PAD_LEFT), $k - $emLen);
        $hLen = 32;
        $sLen = 32;
        if ($emLen < $hLen + $sLen + 2 || $em[$emLen - 1] !== "\xbc") {
            return false;
        }
        $maskedDb = substr($em, 0, $emLen - $hLen - 1);
        $h = substr($em, $emLen - $hLen - 1, $hLen);
        $zeroBits = 8 * $emLen - $emBits;
        if ((ord($maskedDb[0]) >> (8 - $zeroBits)) !== 0 && $zeroBits > 0) {
            return false;
        }
        $mask = '';
        for ($i = 0; strlen($mask) < strlen($maskedDb); $i++) {
            $mask .= hash('sha256', $h . pack('N', $i), true);
        }
        $db = $maskedDb ^ substr($mask, 0, strlen($maskedDb));
        if ($zeroBits > 0) {
            $db[0] = chr(ord($db[0]) & (0xFF >> $zeroBits));
        }
        $pad = $emLen - $hLen - $sLen - 2;
        if (substr($db, 0, $pad) !== str_repeat("\0", $pad) || $db[$pad] !== "\x01") {
            return false;
        }
        $salt = substr($db, -$sLen);

        return hash_equals($h, hash('sha256', str_repeat("\0", 8) . hash('sha256', $message, true) . $salt, true));
    }

    /** Number of significant bits in the top byte of a big-endian integer. */
    private static function topBits(string $n): int
    {
        return strlen(decbin(ord($n[0])));
    }
}
