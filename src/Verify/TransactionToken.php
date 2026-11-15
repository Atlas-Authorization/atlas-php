<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Firebase\JWT\JWT;

/**
 * Verify a Transaction Token (draft-ietf-oauth-transaction-tokens) a service received: `typ: txntoken+jwt`, signed by the issuer's keys,
 * for THIS trust domain, unexpired, with `txn`, `sub`, `scope`, `req_wl`, `iat` and `exp`.
 *
 *     $result = TransactionToken::verify($token, issuer: $iss, trustDomain: 'orders.example.com', jwks: $jwks);
 *     if ($result['ok']) { $result['claims']; $result['scopes']; }
 */
final class TransactionToken
{
    /**
     * @param array{keys:list<array<string,mixed>>} $jwks the issuer's key set (fetch and cache it with {@see JwksCache})
     * @param int|null                              $now  epoch seconds (tests)
     *
     * @return array{ok:true,claims:array<string,mixed>,scopes:list<string>}|array{ok:false,error:string}
     */
    public static function verify(string $token, string $issuer, string $trustDomain, array $jwks, ?int $now = null): array
    {
        $issuer = rtrim($issuer, '/');
        $invalid = ['ok' => false, 'error' => 'The transaction token is invalid, expired, or for another trust domain.'];
        $parts = explode('.', $token);
        $header = count($parts) === 3 ? json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true), true) : null;
        if (!is_array($header) || ($header['typ'] ?? null) !== 'txntoken+jwt') {
            return $invalid;
        }
        $previous = [JWT::$leeway, JWT::$timestamp];
        JWT::$leeway = 5;
        JWT::$timestamp = $now;
        try {
            $claims = json_decode(json_encode(SigningKeys::decode($token, $jwks), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $invalid;
        } finally {
            [JWT::$leeway, JWT::$timestamp] = $previous;
        }
        if (rtrim((string) ($claims['iss'] ?? ''), '/') !== $issuer || !in_array($trustDomain, (array) ($claims['aud'] ?? []), true)) {
            return $invalid;
        }
        foreach (['txn', 'sub', 'scope', 'req_wl', 'iat', 'exp'] as $claim) {
            if (!isset($claims[$claim])) {
                return ['ok' => false, 'error' => "The transaction token has no $claim."];
            }
        }

        return ['ok' => true, 'claims' => $claims, 'scopes' => array_values(array_filter(explode(' ', (string) $claims['scope']), static fn (string $s): bool => $s !== ''))];
    }
}
