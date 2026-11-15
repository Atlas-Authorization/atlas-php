<?php

declare(strict_types=1);

namespace Atlas\Rp;

use Atlas\Exception\ConfigException;

/**
 * Authenticated-encrypted cookie values (AES-256-GCM, key derived from your secret) and `Set-Cookie` header builders. Holds the
 * short-lived sign-in state (state, nonce, PKCE verifier) and can hold your own session. A tampered or expired value opens as null.
 */
final class SealedCookie
{
    public const MIN_SECRET_LENGTH = 32;

    public function __construct(private readonly string $secret, private readonly string $purpose = 'atlas-rp')
    {
        if (strlen($secret) < self::MIN_SECRET_LENGTH) {
            throw new ConfigException('The cookie secret needs at least ' . self::MIN_SECRET_LENGTH . ' characters.');
        }
    }

    /** @param array<string,mixed> $payload */
    public function seal(array $payload, int $ttlSeconds, ?int $now = null): string
    {
        $payload['exp'] = ($now ?? time()) + $ttlSeconds;
        $iv = random_bytes(12);
        $cipher = openssl_encrypt(json_encode($payload, JSON_THROW_ON_ERROR), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag, $this->purpose);
        if ($cipher === false) {
            throw new \RuntimeException('Could not seal the cookie.');
        }

        return rtrim(strtr(base64_encode($iv . $tag . $cipher), '+/', '-_'), '=');
    }

    /** @return array<string,mixed>|null null when tampered with, malformed or expired */
    public function open(?string $value, ?int $now = null): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        $raw = base64_decode(strtr($value, '-_', '+/'), true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), $this->purpose);
        $data = $plain === false ? null : json_decode($plain, true);
        if (!is_array($data) || !is_int($data['exp'] ?? null) || $data['exp'] < ($now ?? time())) {
            return null;
        }

        return $data;
    }

    /** A `Set-Cookie` header value: HttpOnly, SameSite=Lax, Secure by default, path `/`. */
    public static function header(string $name, string $value, int $maxAge, bool $secure = true, string $path = '/', string $sameSite = 'Lax'): string
    {
        return rawurlencode($name) . '=' . $value . '; Path=' . $path . '; Max-Age=' . $maxAge . '; HttpOnly; SameSite=' . $sameSite . ($secure ? '; Secure' : '');
    }

    /** A `Set-Cookie` header value that deletes the cookie. */
    public static function clear(string $name, bool $secure = true, string $path = '/'): string
    {
        return self::header($name, '', 0, $secure, $path);
    }

    private function key(): string
    {
        return hash('sha256', 'atlas-rp-cookie:' . $this->secret, true);
    }
}
