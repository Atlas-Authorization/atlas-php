<?php

declare(strict_types=1);

namespace Atlas;

/**
 * Recognise Atlas keys in either format: the suite format `atlas_<env>_<kind>_<random>` (env `live`/`test`;
 * kind `sk`, `pk`, `psk`, `whsec`) and the legacy `sk_live_…` / `pk_test_…` / `psk_…` / `whsec_…`.
 * Use these instead of `str_starts_with($key, 'sk_')`, which refuses the new format.
 */
final class KeyFormat
{
    /** Key kinds. */
    public const SECRET = 'sk';
    public const PUBLISHABLE = 'pk';
    public const PLATFORM = 'psk';
    public const WEBHOOK_SECRET = 'whsec';

    /**
     * @return array{kind:string,env:string|null,format:'atlas'|'legacy'}|null null when `$key` is not an Atlas key
     */
    public static function parse(string $key): ?array
    {
        if (preg_match('/^atlas_(live|test|dev|sandbox|[a-z0-9]{2,12})_(sk|pk|psk|whsec)_[A-Za-z0-9_\-]+$/', $key, $m) === 1) {
            return ['kind' => $m[2], 'env' => $m[1], 'format' => 'atlas'];
        }
        if (preg_match('/^(sk|pk|psk)_(live|test)_[A-Za-z0-9_\-]+$/', $key, $m) === 1) {
            return ['kind' => $m[1], 'env' => $m[2], 'format' => 'legacy'];
        }
        if (preg_match('/^(sk|pk|psk)_[A-Za-z0-9][A-Za-z0-9_\-]*$/', $key, $m) === 1) {
            return ['kind' => $m[1], 'env' => null, 'format' => 'legacy'];
        }
        if (preg_match('/^whsec_[A-Za-z0-9_\-+\/=]+$/', $key) === 1) {
            return ['kind' => 'whsec', 'env' => null, 'format' => 'legacy'];
        }

        return null;
    }

    /** The key's kind (`sk`, `pk`, `psk`, `whsec`), or null. */
    public static function kind(string $key): ?string
    {
        return self::parse($key)['kind'] ?? null;
    }

    public static function isSecretKey(string $key): bool
    {
        return self::kind($key) === self::SECRET;
    }

    public static function isPublishableKey(string $key): bool
    {
        return self::kind($key) === self::PUBLISHABLE;
    }

    public static function isPlatformKey(string $key): bool
    {
        return self::kind($key) === self::PLATFORM;
    }

    public static function isWebhookSecret(string $key): bool
    {
        return self::kind($key) === self::WEBHOOK_SECRET;
    }
}
