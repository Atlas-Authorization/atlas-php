<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;

/**
 * The JWS algorithms Atlas signs tokens with, and the JWKS parsing that pins
 * every verifier to them. An instance key is RS256 (the default) or ES256, and an
 * API may use either. Each published key is bound to the ONE algorithm its type
 * allows (an RSA key → RS256, a P-256 key → ES256); a key Atlas never signs with,
 * or whose own `alg` contradicts its type, is dropped. So `alg: none`, HMAC, or
 * an algorithm coerced onto the key material never verifies.
 */
final class SigningKeys
{
    /** @var list<string> */
    public const ALGS = ['RS256', 'ES256'];

    /**
     * @param array{keys:array<int,array<string,mixed>>} $jwks
     *
     * @return array<string,Key> keyed by kid
     */
    public static function parse(array $jwks): array
    {
        $keys = [];
        foreach ($jwks['keys'] as $jwk) {
            if (!is_array($jwk)) {
                continue;
            }
            $alg = self::algFor($jwk);
            if ($alg === null) {
                continue;
            }
            $jwk['alg'] = $alg;
            $keys[] = $jwk;
        }
        if ($keys === []) {
            throw new \UnexpectedValueException('No RS256 or ES256 keys in the JWKS.');
        }

        return JWK::parseKeySet(['keys' => $keys]);
    }

    /** @param array<string,mixed> $jwk */
    private static function algFor(array $jwk): ?string
    {
        $kty = $jwk['kty'] ?? null;
        if ($kty === 'RSA') {
            $alg = 'RS256';
        } elseif ($kty === 'EC' && ($jwk['crv'] ?? null) === 'P-256') {
            $alg = 'ES256';
        } else {
            return null;
        }
        $declared = $jwk['alg'] ?? null;

        return $declared === null || $declared === $alg ? $alg : null;
    }
}
