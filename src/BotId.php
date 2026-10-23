<?php

declare(strict_types=1);

namespace Atlas;

use Atlas\Exception\ConfigException;

/**
 * Vercel BotID for Atlas: the signing half that runs next to your BotID check.
 *
 * BotID's verdict (`checkBotId()`) only exists inside a Vercel-hosted request, so that
 * route turns it into a short signed attestation; the browser sends it to Atlas as
 * `captcha_token`, and Atlas checks the signature, freshness and `is_bot === false`, and
 * spends it (each attestation passes once). The same secret is stored in Atlas for the
 * `vercel_botid` captcha provider.
 *
 * Format (version 1): `atlas_botid.v1.<payload>.<sig>` — `payload` is base64url JSON
 * `{v: 1, is_bot, verified_bot, iat, exp, jti}`; `sig` is base64url HMAC-SHA256 over
 * `atlas_botid.v1.<payload>` keyed by the shared secret.
 */
final class BotId
{
    public const PREFIX = 'atlas_botid.v1.';
    public const MAX_TTL_SECONDS = 300;
    public const SECRET_MIN_LENGTH = 32;

    /**
     * Sign a BotID verdict for Atlas. Throws when the secret is shorter than 32
     * characters. A bot verdict is still signed — Atlas refuses it. `$ttlSeconds` is
     * clamped to 1–300.
     */
    public static function createAttestation(
        string $secret,
        bool $isBot,
        bool $isVerifiedBot = false,
        int $ttlSeconds = 60,
        ?int $now = null,
    ): string {
        if (strlen($secret) < self::SECRET_MIN_LENGTH) {
            throw new ConfigException('The BotID attestation secret must be at least ' . self::SECRET_MIN_LENGTH . ' characters.');
        }
        $ttl = max(1, min($ttlSeconds, self::MAX_TTL_SECONDS));
        $iat = $now ?? time();
        $payload = self::b64u(json_encode([
            'v' => 1,
            'is_bot' => $isBot,
            'verified_bot' => $isVerifiedBot,
            'iat' => $iat,
            'exp' => $iat + $ttl,
            'jti' => self::b64u(random_bytes(18)),
        ], JSON_THROW_ON_ERROR));
        $sig = hash_hmac('sha256', self::PREFIX . $payload, $secret, true);

        return self::PREFIX . $payload . '.' . self::b64u($sig);
    }

    private static function b64u(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
