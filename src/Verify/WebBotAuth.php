<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Web Bot Auth: verify who is behind an automated request. A bot signs its requests with an Ed25519 key (RFC 9421 HTTP Message Signatures,
 * tag `web-bot-auth`) and names where its public keys live in the `Signature-Agent` header; the key is found in the directory served at
 * `<Signature-Agent>/.well-known/http-message-signatures-directory` and the signature's `keyid` is the RFC 7638 thumbprint of that key.
 *
 * Needs `ext-sodium`. Covered components supported: `@method`, `@authority`, `@path`, `@query`, `@scheme`, `@target-uri`,
 * `@request-target` and plain header fields (no `;sf`, `;bs`, `;key` or `@query-param` parameters: those answer `unsupported_component`).
 *
 *     $result = WebBotAuth::verify($method, $absoluteUrl, $headers, WebBotAuth::httpKeys($client, $requests));
 *     if ($result['verified']) { $result['directory']; $result['keyId']; }
 */
final class WebBotAuth
{
    public const TAG = 'web-bot-auth';
    public const DIRECTORY_PATH = '/.well-known/http-message-signatures-directory';
    public const DEFAULT_SKEW_SECONDS = 30;
    public const DEFAULT_MAX_LIFETIME_SECONDS = 3600;

    /**
     * @param array<string,string|list<string>>                          $headers
     * @param callable(string,bool):(list<array<string,mixed>>|null)      $getKeys   `(directoryUrl, refresh)` -> Ed25519 JWKs, or null when unavailable
     * @param (callable(string,int):bool)|null                            $claimNonce replay guard: `(key, expiresAtMs)` -> true the first time a nonce is seen
     * @param int|null                                                    $now        epoch seconds (tests)
     *
     * @return array{signed:bool,verified:bool,reason?:string,directory?:string,keyId?:string,label?:string,created?:int,expires?:int,nonce?:?string}
     */
    public static function verify(
        string $method,
        string $url,
        array $headers,
        callable $getKeys,
        ?int $now = null,
        int $skewSeconds = self::DEFAULT_SKEW_SECONDS,
        int $maxLifetimeSeconds = self::DEFAULT_MAX_LIFETIME_SECONDS,
        ?callable $claimNonce = null,
    ): array {
        $inputHeader = self::header($headers, 'signature-input');
        $sigHeader = self::header($headers, 'signature');
        if ($inputHeader === null || $sigHeader === null) {
            return ['signed' => false, 'verified' => false, 'reason' => 'no_signature'];
        }
        $fail = static fn (string $reason, array $extra = []): array => ['signed' => true, 'verified' => false, 'reason' => $reason] + $extra;

        // Signature-Input: label=("a" "b");created=1;expires=2;keyid="k";tag="web-bot-auth" (first member carrying our tag wins).
        $input = null;
        foreach (self::splitTop($inputHeader) as $member) {
            if (preg_match('/^([a-z*][a-z0-9_.*-]*)=(\((?:[^()]*)\)(?:;[^,]*)?)$/', trim($member), $m) === 1 && str_contains($m[2], 'tag="' . self::TAG . '"')) {
                $input = ['label' => $m[1], 'raw' => $m[2]];
                break;
            }
        }
        if ($input === null) {
            return $fail('no_web_bot_auth_signature');
        }
        if (preg_match('/^\(([^()]*)\)(.*)$/', $input['raw'], $m) !== 1) {
            return $fail('malformed');
        }
        preg_match_all('/"([^"]+)"(;[^\s"]*)?/', $m[1], $cm, PREG_SET_ORDER);
        $components = [];
        foreach ($cm as $c) {
            if (($c[2] ?? '') !== '') {
                return $fail('unsupported_component', ['label' => $input['label']]);
            }
            $components[] = $c[1];
        }
        $params = [];
        foreach (explode(';', ltrim($m[2], ';')) as $kv) {
            if ($kv === '') {
                continue;
            }
            [$k, $v] = array_pad(explode('=', $kv, 2), 2, 'true');
            $params[$k] = trim($v, '"');
        }
        $sig = null;
        foreach (self::splitTop($sigHeader) as $member) {
            if (preg_match('/^' . preg_quote($input['label'], '/') . '=:([A-Za-z0-9+\/=]*):$/', trim($member), $sm) === 1) {
                $sig = base64_decode($sm[1], true);
            }
        }
        if ($sig === null || $sig === false) {
            return $fail('malformed', ['label' => $input['label']]);
        }
        $created = $params['created'] ?? null;
        $expires = $params['expires'] ?? null;
        $keyId = $params['keyid'] ?? '';
        $nonce = $params['nonce'] ?? null;
        $base = ['label' => $input['label'], 'keyId' => $keyId, 'created' => (int) $created, 'expires' => (int) $expires];
        if (!ctype_digit((string) $created) || !ctype_digit((string) $expires) || $keyId === '' || strlen($keyId) > 128 || ($nonce !== null && strlen($nonce) > 512)) {
            return $fail('bad_params', ['label' => $input['label']]);
        }
        if (isset($params['alg']) && $params['alg'] !== 'ed25519') {
            return $fail('unsupported_algorithm', $base);
        }
        $nowSec = $now ?? time();
        if ($base['expires'] <= $base['created']) {
            return $fail('bad_params', $base);
        }
        if ($base['expires'] - $base['created'] > $maxLifetimeSeconds) {
            return $fail('lifetime_too_long', $base);
        }
        if ($base['created'] > $nowSec + $skewSeconds) {
            return $fail('not_yet_valid', $base);
        }
        if ($base['expires'] < $nowSec - $skewSeconds) {
            return $fail('expired', $base);
        }
        // Coverage: the signature must bind the host and the directory claim it relies on.
        $agent = self::header($headers, 'signature-agent');
        if ($agent === null) {
            return $fail('missing_signature_agent', $base);
        }
        if (!in_array('@authority', $components, true) || !in_array('signature-agent', $components, true)) {
            return $fail('components_not_covered', $base);
        }
        $directory = self::directoryOf($agent, $input['label']);
        if ($directory === null) {
            return $fail('bad_signature_agent', $base);
        }
        $base['directory'] = $directory;

        $lines = [];
        $parts = parse_url($url);
        foreach ($components as $name) {
            $value = self::component($name, $method, $parts, $url, $headers);
            if ($value === null || preg_match('/[\r\n]/', $value) === 1) {
                return $fail($name !== strtolower($name) || (str_starts_with($name, '@') && !in_array($name, ['@method', '@authority', '@path', '@query', '@scheme', '@target-uri', '@request-target'], true)) ? 'unsupported_component' : 'component_missing', $base);
            }
            $lines[] = '"' . $name . '": ' . $value;
        }
        $lines[] = '"@signature-params": ' . $input['raw'];
        $signed = implode("\n", $lines);

        $dirUrl = $directory . self::DIRECTORY_PATH;
        $find = static function (?array $keys) use ($keyId): ?array {
            foreach ($keys ?? [] as $jwk) {
                if (is_array($jwk) && self::thumbprint($jwk) === $keyId) {
                    return $jwk;
                }
            }

            return null;
        };
        $keys = $getKeys($dirUrl, false);
        $jwk = $find($keys);
        if ($jwk === null) {
            $keys = $getKeys($dirUrl, true);
            $jwk = $find($keys);
        }
        if ($jwk === null) {
            return $fail($keys === null ? 'directory_unavailable' : 'key_not_found', $base);
        }
        $pub = self::b64uDecode((string) ($jwk['x'] ?? ''));
        if ($pub === null || strlen($pub) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES || !sodium_crypto_sign_verify_detached($sig, $signed, $pub)) {
            return $fail('bad_signature', $base);
        }
        if ($claimNonce !== null && $nonce !== null && !$claimNonce($directory . '|' . $keyId . '|' . $nonce, ($base['expires'] + $skewSeconds) * 1000)) {
            return $fail('replayed', $base);
        }

        return ['signed' => true, 'verified' => true, 'nonce' => $nonce] + $base;
    }

    /**
     * A key source that fetches a directory over PSR-18 and caches it for `$ttlSeconds` (a `refresh` fetches again, at most once a minute).
     *
     * @return callable(string,bool):(list<array<string,mixed>>|null)
     */
    public static function httpKeys(ClientInterface $client, RequestFactoryInterface $requests, int $ttlSeconds = 3600): callable
    {
        $cache = [];

        return static function (string $directoryUrl, bool $refresh) use (&$cache, $client, $requests, $ttlSeconds): ?array {
            $hit = $cache[$directoryUrl] ?? null;
            $age = $hit === null ? PHP_INT_MAX : time() - $hit['at'];
            if ($hit !== null && $age < $ttlSeconds && (!$refresh || $age < 60)) {
                return $hit['keys'];
            }
            try {
                $response = $client->sendRequest($requests->createRequest('GET', $directoryUrl)->withHeader('Accept', 'application/http-message-signatures-directory+json, application/json'));
                $doc = json_decode((string) $response->getBody(), true);
            } catch (\Throwable) {
                return $hit['keys'] ?? null;
            }
            if ($response->getStatusCode() !== 200 || !is_array($doc['keys'] ?? null)) {
                return $hit['keys'] ?? null;
            }
            $keys = array_values(array_filter($doc['keys'], static fn ($k): bool => is_array($k) && ($k['kty'] ?? null) === 'OKP' && ($k['crv'] ?? null) === 'Ed25519' && is_string($k['x'] ?? null)));
            $cache[$directoryUrl] = ['at' => time(), 'keys' => $keys];

            return $keys;
        };
    }

    /** RFC 7638 thumbprint (base64url SHA-256) of an Ed25519 public JWK: the web-bot-auth `keyid`. @param array<string,mixed> $jwk */
    public static function thumbprint(array $jwk): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', '{"crv":"Ed25519","kty":"OKP","x":"' . ($jwk['x'] ?? '') . '"}', true)), '+/', '-_'), '=');
    }

    private static function directoryOf(string $value, string $label): ?string
    {
        $v = trim($value);
        if (preg_match('/^[a-z*][a-z0-9_.*-]*=/', $v) === 1) {
            $members = self::splitTop($v);
            $pick = $members[0];
            foreach ($members as $mem) {
                if (str_starts_with(trim($mem), $label . '=')) {
                    $pick = $mem;
                }
            }
            $v = trim(substr($pick, strpos($pick, '=') + 1));
        }
        $v = trim($v, '"');
        if ($v === '') {
            return null;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $v) !== 1) {
            $v = 'https://' . $v;
        }
        $p = parse_url($v);
        if ($p === false || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])) {
            return null;
        }

        return 'https://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . rtrim($p['path'] ?? '', '/');
    }

    /**
     * @param array<string,mixed>               $parts
     * @param array<string,string|list<string>> $headers
     */
    private static function component(string $name, string $method, array $parts, string $url, array $headers): ?string
    {
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $port = $parts['port'] ?? null;
        if ($port !== null && (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))) {
            $port = null;
        }

        return match ($name) {
            '@method' => strtoupper($method),
            '@authority' => $host === '' ? null : $host . ($port !== null ? ':' . $port : ''),
            '@scheme' => $scheme,
            '@path' => ($parts['path'] ?? '') === '' ? '/' : $parts['path'],
            '@query' => '?' . ($parts['query'] ?? ''),
            '@target-uri' => $url,
            '@request-target' => (($parts['path'] ?? '') === '' ? '/' : $parts['path']) . (isset($parts['query']) ? '?' . $parts['query'] : ''),
            default => str_starts_with($name, '@') || $name !== strtolower($name) ? null : self::header($headers, $name),
        };
    }

    /** @param array<string,string|list<string>> $headers */
    private static function header(array $headers, string $name): ?string
    {
        $parts = [];
        foreach ($headers as $k => $v) {
            if (strcasecmp((string) $k, $name) === 0) {
                foreach ((array) $v as $item) {
                    $parts[] = trim((string) $item);
                }
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /** @return list<string> top-level comma-separated members (commas inside quotes or parentheses do not split) */
    private static function splitTop(string $text): array
    {
        $out = [];
        $cur = '';
        $depth = 0;
        $quoted = false;
        foreach (str_split($text) as $ch) {
            if ($ch === '"') {
                $quoted = !$quoted;
            } elseif (!$quoted && $ch === '(') {
                $depth++;
            } elseif (!$quoted && $ch === ')') {
                $depth--;
            }
            if ($ch === ',' && !$quoted && $depth === 0) {
                $out[] = $cur;
                $cur = '';
            } else {
                $cur .= $ch;
            }
        }

        return array_values(array_filter([...$out, $cur], static fn (string $s): bool => trim($s) !== ''));
    }

    private static function b64uDecode(string $v): ?string
    {
        $r = base64_decode(strtr($v, '-_', '+/'), true);

        return $r === false ? null : $r;
    }
}
